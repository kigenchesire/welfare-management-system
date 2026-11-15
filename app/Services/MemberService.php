<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Member;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(private WalletService $wallets, private SmsService $sms)
    {
    }

    /**
     * Save a registration as PENDING (online form or office registration).
     * $beneficiaries: [['name' => .., 'category' => ..], ...]
     */
    public function register(array $data, array $beneficiaries, string $source = 'online'): Member
    {
        $member = DB::transaction(function () use ($data, $beneficiaries, $source) {
            $member = Member::create([
                'full_name' => $data['full_name'],
                'national_id' => $data['national_id'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'residential_address' => $data['residential_address'] ?? null,
                'alternative_contact' => $data['alternative_contact'] ?? null,
                'registration_date' => $data['registration_date'] ?? now()->toDateString(),
                'status' => 'pending',
                'source' => $source,
            ]);
            foreach ($beneficiaries as $b) {
                $this->addBeneficiary($member, $b['name'], $b['category'], false);
            }
            AuditLogger::log('member.registered', $member, null, $member->only(['full_name', 'national_id', 'phone', 'source']));

            return $member;
        });

        NotificationService::toRoles(['Secretary', 'Administrator'], 'registration', 'New member registration',
            $member->full_name.' (ID '.$member->national_id.') has applied for membership.', route('members.show', $member));

        return $member;
    }

    /** Approve a pending registration and create the login account (password 123456). */
    public function approve(Member $member): Member
    {
        DB::transaction(function () use ($member) {
            $member = Member::lockForUpdate()->find($member->id);
            if ($member->status !== 'pending') {
                throw ValidationException::withMessages(['member' => 'Only pending registrations can be approved.']);
            }

            $user = User::withTrashed()->where('username', $member->national_id)->first();
            if ($user && $user->member && $user->member->id !== $member->id) {
                throw ValidationException::withMessages(['member' => 'A login account with this National ID already exists.']);
            }
            $emailTaken = $member->email && User::where('email', $member->email)->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists();

            $user ??= new User();
            $user->fill([
                'name' => $member->full_name,
                'username' => $member->national_id,
                'email' => $emailTaken ? null : $member->email,
                'phone' => $member->phone,
                'password' => User::DEFAULT_PASSWORD,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->deleted_at = null;
            $user->save();
            $user->assignRole('Member');

            $prefix = Settings::get('member_no_prefix', 'M');
            $member->update([
                'user_id' => $user->id,
                'member_no' => $prefix.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT),
                'status' => 'active',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $this->wallets->walletFor($member);

            AuditLogger::log('member.approved', $member, ['status' => 'pending'], ['status' => 'active', 'member_no' => $member->member_no, 'user_id' => $user->id]);
        });

        $member->refresh();
        try {
            NotificationService::toRoles(['Administrator', 'Chairperson', 'Treasurer'], 'registration', 'Registration approved',
                $member->full_name.' is now member '.$member->member_no.'.', route('members.show', $member));
            NotificationService::toMember($member, 'registration', 'Welcome', 'Your membership has been approved. Please change your password.', route('password.change'));
            $this->sms->send($member->phone, $this->credentialsMessage($member), 'credentials', $member);
        } catch (\Throwable $e) {
            report($e);
        }

        return $member;
    }

    public function reject(Member $member, string $reason): void
    {
        if ($member->status !== 'pending') {
            throw ValidationException::withMessages(['member' => 'Only pending registrations can be rejected.']);
        }
        $member->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'rejection_reason' => $reason]);
        AuditLogger::log('member.rejected', $member, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason]);
        NotificationService::toRoles(['Administrator', 'Secretary'], 'registration', 'Registration rejected',
            $member->full_name.' was rejected: '.$reason, route('members.show', $member));
        $this->sms->send($member->phone, 'Dear '.$member->full_name.', your registration with '.Settings::get('group_name').' was not approved. Reason: '.$reason, 'registration', $member);
    }

    /** Suspend, reactivate or mark inactive. Login is disabled unless the member is active. */
    public function changeStatus(Member $member, string $status, ?string $reason): void
    {
        $old = $member->status;
        $member->update(['status' => $status, 'status_reason' => $reason]);
        $member->user?->update(['is_active' => $status === 'active']);
        AuditLogger::log('member.status_changed', $member, ['status' => $old], ['status' => $status, 'reason' => $reason]);
    }

    public function addBeneficiary(Member $member, string $name, string $category, bool $audit = true): Beneficiary
    {
        $this->checkLimit($member, $category);
        $b = $member->beneficiaries()->create(['name' => trim($name), 'category' => $category, 'created_by' => auth()->id()]);
        if ($audit) {
            AuditLogger::log('beneficiary.added', $b, null, $b->only(['member_id', 'name', 'category']));
            NotificationService::toMember($member, 'beneficiary', 'Beneficiary added', $b->name.' ('.$b->category_label.') was added to your beneficiaries.', route('member.beneficiaries'));
        }

        return $b;
    }

    public function updateBeneficiary(Beneficiary $b, string $name, string $category): void
    {
        if ($category !== $b->category) {
            $this->checkLimit($b->member, $category, $b->id);
        }
        $b->fill(['name' => trim($name), 'category' => $category, 'updated_by' => auth()->id()]);
        AuditLogger::logChanges('beneficiary.updated', $b);
        $b->save();
    }

    public function removeBeneficiary(Beneficiary $b): void
    {
        if ($b->is_deceased) {
            throw ValidationException::withMessages(['beneficiary' => 'This beneficiary is linked to a welfare case and cannot be removed.']);
        }
        AuditLogger::log('beneficiary.removed', $b, $b->only(['member_id', 'name', 'category']), null);
        $b->delete();
    }

    public function checkLimit(Member $member, string $category, ?int $ignoreId = null): void
    {
        $limit = Beneficiary::limit($category);
        if ($limit === null) {
            return;
        }
        $count = $member->beneficiaries()->where('category', $category)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->count();
        if ($count >= $limit) {
            throw ValidationException::withMessages(['category' => 'A member can have at most '.$limit.' '.strtolower(Beneficiary::label($category)).' beneficiar'.($limit === 1 ? 'y' : 'ies').'.']);
        }
    }

    public function credentialsMessage(Member $member): string
    {
        return SmsService::render('sms_tpl_credentials', [
            'name' => $member->full_name,
            'member_no' => $member->member_no,
            'username' => $member->national_id,
            'password' => User::DEFAULT_PASSWORD,
            'login_url' => route('login'),
        ]);
    }
}
