<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\WelfareCase;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WelfareCaseService
{
    public function __construct(private WalletService $wallets, private SmsService $sms)
    {
    }

    /**
     * Create a case for a bereavement and raise contribution invoices.
     * $beneficiaryId null means the member themself has passed on ("self" case).
     */
    public function create(Member $member, ?int $beneficiaryId, string $bereavementDate, ?string $remarks): WelfareCase
    {
        if ($member->status !== 'active') {
            throw ValidationException::withMessages(['member_id' => 'Cases can only be raised for active members.']);
        }

        $beneficiary = null;
        if ($beneficiaryId) {
            $beneficiary = $member->beneficiaries()->whereKey($beneficiaryId)->first();
            if (! $beneficiary) {
                throw ValidationException::withMessages(['beneficiary_id' => 'The selected beneficiary does not belong to this member.']);
            }
            if ($beneficiary->is_deceased) {
                throw ValidationException::withMessages(['beneficiary_id' => 'A case has already been raised for this beneficiary.']);
            }
        }

        $duplicate = WelfareCase::where('member_id', $member->id)
            ->where('status', '!=', 'cancelled')
            ->when($beneficiary, fn ($q) => $q->where('beneficiary_id', $beneficiary->id), fn ($q) => $q->where('category', 'self'))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['beneficiary_id' => 'A case already exists for this bereavement.']);
        }

        $category = $beneficiary ? $beneficiary->category : 'self';
        $amount = Settings::contributionFor($category);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['beneficiary_id' => 'No contribution amount is configured for '.WelfareCase::CATEGORIES[$category].' cases. Set it in System Settings > Financial.']);
        }

        $case = DB::transaction(function () use ($member, $beneficiary, $category, $amount, $bereavementDate, $remarks) {
            $case = WelfareCase::create([
                'case_no' => 'TMP'.uniqid(),
                'member_id' => $member->id,
                'beneficiary_id' => $beneficiary?->id,
                'category' => $category,
                'deceased_name' => $beneficiary ? $beneficiary->name : $member->full_name,
                'bereavement_date' => $bereavementDate,
                'remarks' => $remarks,
                'contribution_amount' => $amount,
                // Taken first from each member's payment: office, then transport, the rest is welfare.
                'office_share' => Settings::money('office_share_per_case'),
                'transport_share' => Settings::money('transport_share_per_case'),
                'status' => 'open',
                'created_by' => auth()->id(),
            ]);
            $case->update(['case_no' => 'WC'.$case->created_at->format('Y').'-'.str_pad((string) $case->id, 4, '0', STR_PAD_LEFT)]);

            if ($beneficiary) {
                $beneficiary->update(['is_deceased' => true, 'date_of_death' => $bereavementDate, 'updated_by' => auth()->id()]);
            } else {
                $member->update(['status' => 'inactive', 'status_reason' => 'Deceased']);
            }

            AuditLogger::log('case.created', $case, null, $case->only([
                'case_no', 'member_id', 'beneficiary_id', 'category', 'deceased_name', 'bereavement_date', 'contribution_amount',
            ]));

            return $case;
        });

        $this->process($case);

        return $case->fresh();
    }

    /**
     * Raise an invoice for every eligible member who doesn't have one yet and
     * deduct what each wallet can cover. Safe to run more than once: members
     * already invoiced are skipped (also enforced by a unique index).
     */
    public function process(WelfareCase $case): array
    {
        if (! $case->isActive()) {
            throw ValidationException::withMessages(['case' => 'Closed or cancelled cases cannot be processed.']);
        }

        $result = DB::transaction(function () use ($case) {
            $case = WelfareCase::lockForUpdate()->find($case->id);
            $already = $case->invoices()->pluck('member_id')->all();

            // Every active member contributes, including the bereaved member on their own case.
            // Only a member who has died ("self" case) is not invoiced.
            $eligible = Member::active()
                ->when($case->category === 'self', fn ($q) => $q->where('id', '!=', $case->member_id))
                ->whereNotIn('id', $already)
                ->get();

            $created = [];
            foreach ($eligible as $m) {
                $invoice = Invoice::create([
                    'invoice_no' => 'TMP'.uniqid('', true),
                    'member_id' => $m->id,
                    'welfare_case_id' => $case->id,
                    'type' => 'welfare',
                    'category' => $case->category,
                    'title' => 'Welfare contribution: '.$case->case_no.' ('.$case->deceased_name.')',
                    'description' => $case->category_label.' case for member '.$case->member->full_name,
                    'amount' => $case->contribution_amount,
                    'invoice_date' => now()->toDateString(),
                    'status' => 'unpaid',
                    'created_by' => auth()->id(),
                ]);
                $invoice->update(['invoice_no' => 'INV'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)]);
                $this->wallets->applyToInvoice($invoice);
                $created[] = $invoice->id;
            }

            $case->members_invoiced = $case->invoices()->where('status', '!=', 'cancelled')->count();
            $case->processed_at = now();
            $case->save();
            $this->refreshStatus($case);

            if ($created) {
                AuditLogger::log('invoice.generated', $case, null, ['invoices_created' => count($created)], 'Contribution invoices generated for '.$case->case_no);
            }

            return ['created' => $created, 'case' => $case];
        });

        if ($result['created']) {
            $this->announce($result['case'], $result['created']);
        }

        return ['created' => count($result['created'])];
    }

    public function refreshStatus(WelfareCase $case): void
    {
        if (! $case->isActive()) {
            return;
        }
        $s = $case->summary();
        $status = match (true) {
            $s['invoices'] === 0 => 'open',
            $s['outstanding'] <= 0 => 'fully_collected',
            $s['collected'] > 0 => 'in_progress',
            default => 'open',
        };
        if ($status !== $case->status) {
            $case->update(['status' => $status]);
        }
    }

    public function close(WelfareCase $case): void
    {
        if (! $case->isActive()) {
            throw ValidationException::withMessages(['case' => 'This case is already closed or cancelled.']);
        }
        $old = $case->status;
        $case->update(['status' => 'closed', 'closed_by' => auth()->id(), 'closed_at' => now()]);
        AuditLogger::log('case.closed', $case, ['status' => $old], ['status' => 'closed']);
    }

    /**
     * Cancel a case: every invoice is cancelled and any amount already deducted
     * is refunded to the member's wallet through the ledger.
     */
    public function cancel(WelfareCase $case, string $reason): void
    {
        DB::transaction(function () use ($case, $reason) {
            $case = WelfareCase::lockForUpdate()->find($case->id);
            if ($case->status === 'cancelled') {
                throw ValidationException::withMessages(['case' => 'This case is already cancelled.']);
            }
            if ($case->disbursements()->active()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Money has already been disbursed for this case. Void the disbursements first, then cancel the case.']);
            }
            $old = $case->status;

            foreach ($case->invoices()->where('status', '!=', 'cancelled')->get() as $invoice) {
                $this->wallets->refundInvoice($invoice, 'case '.$case->case_no.' cancelled');
                $invoice->update([
                    'status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(),
                    'cancellation_reason' => 'Case cancelled: '.$reason,
                ]);
            }

            if ($case->beneficiary_id) {
                Beneficiary::withTrashed()->whereKey($case->beneficiary_id)->update(['is_deceased' => false, 'date_of_death' => null]);
            } elseif ($case->member->status === 'inactive' && $case->member->status_reason === 'Deceased') {
                $case->member->update(['status' => 'active', 'status_reason' => null]);
            }

            $case->update(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            AuditLogger::log('case.cancelled', $case, ['status' => $old], ['status' => 'cancelled', 'reason' => $reason]);
        });
    }

    /**
     * SMS (and dashboard notification) to members who still owe on this case,
     * each with their own outstanding amount. $invoiceIds limits it to selected invoices.
     *
     * @return int number of members reminded
     */
    public function remindUnpaid(WelfareCase $case, ?array $invoiceIds = null): int
    {
        $invoices = $case->invoices()->open()->with('member')
            ->when($invoiceIds, fn ($q) => $q->whereIn('id', $invoiceIds))
            ->get()->filter(fn ($i) => $i->outstanding > 0 && $i->member);

        $messages = [];
        foreach ($invoices as $inv) {
            $amount = number_format($inv->outstanding, 2);
            NotificationService::toMember($inv->member, 'reminder', 'Contribution reminder: '.$case->case_no,
                'Your contribution for '.$case->deceased_name.' ('.$case->case_no.') is outstanding. Please pay '.money($inv->outstanding).'.', route('member.invoices'));
            $messages[] = [$inv->member->phone, SmsService::render('sms_tpl_case_reminder', [
                'name' => $inv->member->full_name,
                'deceased' => $case->deceased_name,
                'category' => $case->category_label,
                'member' => $case->member->full_name,
                'case_no' => $case->case_no,
                'amount' => number_format((float) $inv->amount, 2),
                'outstanding' => $amount,
                'invoice_no' => $inv->invoice_no,
            ]), $inv->member];
        }
        $this->sms->sendLater($messages, 'reminder');

        if ($invoices->isNotEmpty()) {
            AuditLogger::log('case.reminders_sent', $case, null, [
                'members' => $invoices->count(), 'outstanding' => round($invoices->sum(fn ($i) => $i->outstanding), 2),
            ], 'Payment reminders sent for '.$case->case_no);
        }

        return $invoices->count();
    }

    private function announce(WelfareCase $case, array $invoiceIds): void
    {
        try {
            NotificationService::toAllMembers('case', 'New welfare case '.$case->case_no,
                'We regret to announce the passing of '.$case->deceased_name.' ('.$case->category_label.') of member '.$case->member->full_name.'. Contribution: '.money($case->contribution_amount).'.',
                route('member.cases'));
            NotificationService::toRoles(['Administrator', 'Chairperson', 'Treasurer'], 'case', 'Case '.$case->case_no.' raised',
                count($invoiceIds).' contribution invoices generated.', route('cases.show', $case));

            $messages = [];
            Invoice::with('member')->whereIn('id', $invoiceIds)->chunk(300, function ($invoices) use ($case, &$messages) {
                foreach ($invoices as $inv) {
                    $messages[] = [$inv->member->phone, SmsService::render('sms_tpl_case', [
                        'name' => $inv->member->full_name,
                        'deceased' => $case->deceased_name,
                        'category' => $case->category_label,
                        'member' => $case->member->full_name,
                        'case_no' => $case->case_no,
                        'amount' => number_format((float) $inv->amount, 2),
                        'outstanding' => number_format($inv->outstanding, 2),
                    ]), $inv->member];
                }
            });
            $this->sms->sendLater($messages, 'case');
        } catch (\Throwable $e) {
            report($e); // notifications must never undo a case
        }
    }
}
