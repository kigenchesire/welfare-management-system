<?php

namespace App\Services;

use App\Models\CaseDisbursement;
use App\Models\WelfareCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money paid out for a welfare case (to the bereaved family, for transport, ...).
 * A disbursement can never exceed what has been collected for that fund on the case.
 */
class DisbursementService
{
    public function record(WelfareCase $case, array $data): CaseDisbursement
    {
        return DB::transaction(function () use ($case, $data) {
            $case = WelfareCase::lockForUpdate()->findOrFail($case->id);
            if ($case->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Money cannot be disbursed on a cancelled case.']);
            }

            $fund = $data['fund'];
            $amount = round((float) $data['amount'], 2);
            $available = $case->funds()[$fund.'_available'];
            if ($amount > $available) {
                throw ValidationException::withMessages(['amount' => 'Only '.money($available).' of the '.strtolower(CaseDisbursement::FUNDS[$fund]).' collected for this case is still available.']);
            }

            $d = CaseDisbursement::create([
                'reference' => 'TMP'.uniqid('', true),
                'welfare_case_id' => $case->id,
                'fund' => $fund,
                'recipient' => $data['recipient'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'amount' => $amount,
                'disbursement_date' => $data['disbursement_date'],
                'payment_ref' => isset($data['payment_ref']) ? strtoupper(trim($data['payment_ref'])) ?: null : null,
                'remarks' => $data['remarks'] ?? null,
                'status' => 'active',
                'recorded_by' => auth()->id(),
            ]);
            $d->update(['reference' => 'DSB'.str_pad((string) $d->id, 6, '0', STR_PAD_LEFT)]);

            AuditLogger::log('case.disbursed', $d, null, $d->only(['reference', 'welfare_case_id', 'fund', 'recipient', 'amount', 'disbursement_date']),
                money($amount).' disbursed to '.$d->recipient.' for case '.$case->case_no);

            return $d;
        });
    }

    /** Mark a disbursement recorded in error as void. The record is kept for the audit trail. */
    public function void(CaseDisbursement $d, string $reason): void
    {
        DB::transaction(function () use ($d, $reason) {
            $d = CaseDisbursement::lockForUpdate()->findOrFail($d->id);
            if ($d->status !== 'active') {
                throw ValidationException::withMessages(['reason' => 'This disbursement is already void.']);
            }
            $d->update(['status' => 'voided', 'voided_by' => auth()->id(), 'voided_at' => now(), 'void_reason' => $reason]);
            AuditLogger::log('case.disbursement_voided', $d, ['status' => 'active'], ['status' => 'voided', 'reason' => $reason]);
        });
    }
}
