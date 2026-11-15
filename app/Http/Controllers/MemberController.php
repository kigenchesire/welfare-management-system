<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Member;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MemberService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct(private MemberService $members)
    {
    }

    public static function memberRules(?Member $member = null): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('members', 'national_id')->ignore($member?->id)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{9,15}$/'],
            'email' => ['nullable', 'email:filter', 'max:150'],
            'residential_address' => ['nullable', 'string', 'max:200'],
            'alternative_contact' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function index(Request $request)
    {
        $status = $request->input('status');
        $members = Member::with('wallet')
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'rejected'))
            ->orderBy('full_name')->get();

        return view('members.index', compact('members', 'status'));
    }

    public function pending()
    {
        return view('members.pending', ['members' => Member::withCount('beneficiaries')->where('status', 'pending')->oldest()->get()]);
    }

    public function create()
    {
        return view('members.create', ['categories' => Beneficiary::CATEGORIES]);
    }

    /** Office registration by the Secretary. Goes through the same pending > approve workflow. */
    public function store(Request $request)
    {
        $data = $request->validate(self::memberRules() + [
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'beneficiaries' => ['array'],
            'beneficiaries.*.name' => ['nullable', 'string', 'max:150'],
            'beneficiaries.*.category' => ['nullable', Rule::in(array_keys(Beneficiary::CATEGORIES))],
        ]);
        $member = $this->members->register($data, BeneficiaryController::cleanList($request->input('beneficiaries', [])), 'office');

        if ($request->boolean('approve_now') && $request->user()->can('members.approve')) {
            $this->members->approve($member);

            return redirect()->route('members.show', $member)->with('success', 'Member registered and approved. Login: '.$member->national_id.' / '.User::DEFAULT_PASSWORD);
        }

        return redirect()->route('members.show', $member)->with('success', 'Registration saved as pending.');
    }

    public function show(Member $member)
    {
        $member->load(['wallet', 'user', 'approver']);

        return view('members.show', [
            'member' => $member,
            'beneficiaries' => $member->beneficiaries()->orderBy('category')->get(),
            'invoices' => $member->invoices()->latest('invoice_date')->latest('id')->limit(50)->get(),
            'transactions' => $member->walletTransactions()->latest('id')->limit(20)->get(),
            'payments' => $member->payments()->latest('payment_date')->limit(20)->get(),
            'outstanding' => $member->outstandingAmount(),
            'categories' => Beneficiary::CATEGORIES,
        ]);
    }

    public function edit(Member $member)
    {
        return view('members.edit', compact('member'));
    }

    public function update(Request $request, Member $member)
    {
        $data = $request->validate(self::memberRules($member) + ['registration_date' => ['required', 'date']]);

        $member->fill($data);
        AuditLogger::logChanges('member.updated', $member);
        $member->save();

        // Keep the login account in step with the member record.
        if ($member->user) {
            $emailFree = ! $member->email || ! User::where('email', $member->email)->whereKeyNot($member->user_id)->exists();
            $member->user->update([
                'name' => $member->full_name,
                'username' => $member->national_id,
                'phone' => $member->phone,
                'email' => $emailFree ? $member->email : $member->user->email,
            ]);
        }

        return redirect()->route('members.show', $member)->with('success', 'Member details updated.');
    }

    public function approve(Member $member)
    {
        $this->members->approve($member);

        return redirect()->route('members.show', $member)
            ->with('success', $member->full_name.' approved as '.$member->fresh()->member_no.'. Login: '.$member->national_id.' / '.User::DEFAULT_PASSWORD);
    }

    public function reject(Request $request, Member $member)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $this->members->reject($member, $data['reason']);

        return redirect()->route('members.pending')->with('success', 'Registration rejected.');
    }

    public function changeStatus(Request $request, Member $member)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);
        abort_if(in_array($member->status, ['pending', 'rejected'], true), 422, 'Approve the registration first.');
        $this->members->changeStatus($member, $data['status'], $data['reason'] ?? null);

        return back()->with('success', 'Membership status changed to '.Member::STATUSES[$data['status']].'.');
    }

    public function resetPassword(Member $member, SmsService $sms)
    {
        abort_unless($member->user, 422, 'This member has no login account yet.');
        $member->user->forceFill(['password' => User::DEFAULT_PASSWORD, 'must_change_password' => true])->save();
        AuditLogger::log('user.password_reset', $member->user, null, null, 'Password reset to default for '.$member->full_name);
        $sms->send($member->phone, $this->members->credentialsMessage($member), 'credentials', $member);

        return back()->with('success', 'Password reset to the default ('.User::DEFAULT_PASSWORD.'). Login details sent by SMS.');
    }

    /** JSON for the case form and deposit search. */
    public function search(Request $request)
    {
        $term = trim((string) $request->input('q'));
        $members = Member::with('wallet')->whereIn('status', ['active', 'suspended', 'inactive'])
            ->when($term, fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('national_id', 'like', "%{$term}%")->orWhere('member_no', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('full_name')->limit(20)->get();

        return response()->json($members->map(fn ($m) => [
            'id' => $m->id,
            'text' => $m->full_name.' - ID '.$m->national_id.($m->member_no ? ' ('.$m->member_no.')' : ''),
            'status' => $m->status,
            'balance' => money($m->balance),
        ]));
    }

    public function beneficiariesJson(Member $member)
    {
        return response()->json($member->beneficiaries()->where('is_deceased', false)->orderBy('category')->get()->map(fn ($b) => [
            'id' => $b->id, 'name' => $b->name, 'category' => $b->category, 'label' => $b->category_label,
        ]));
    }
}
