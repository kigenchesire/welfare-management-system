<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private WalletService $wallets, private SmsService $sms)
    {
    }

    /** Raise the same custom invoice (airtime, lunch, ...) for each selected member. */
    public function createCustom(array $memberIds, array $data, bool $deduct, bool $sendSms): int
    {
        $batch = 'B'.now()->format('ymdHis').strtoupper(Str::random(4));

        $ids = DB::transaction(function () use ($memberIds, $data, $deduct, $batch) {
            $ids = [];
            foreach (Member::whereIn('id', $memberIds)->get() as $member) {
                $invoice = Invoice::create([
                    'invoice_no' => 'TMP'.uniqid('', true),
                    'member_id' => $member->id,
                    'type' => 'custom',
                    'category' => $data['category'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'amount' => round((float) $data['amount'], 2),
                    'invoice_date' => now()->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'status' => 'unpaid',
                    'batch_ref' => $batch,
                    'created_by' => auth()->id(),
                ]);
                $invoice->update(['invoice_no' => 'INV'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)]);
                if ($deduct) {
                    $this->wallets->applyToInvoice($invoice);
                }
                $ids[] = $invoice->id;
            }
            AuditLogger::log('invoice.custom_created', null, null, [
                'batch' => $batch, 'title' => $data['title'], 'amount' => $data['amount'], 'members' => count($ids),
            ], 'Custom invoice "'.$data['title'].'" raised for '.count($ids).' member(s)');

            return $ids;
        });

        try {
            $messages = [];
            foreach (Invoice::with('member')->whereIn('id', $ids)->get() as $inv) {
                NotificationService::toMember($inv->member, 'invoice', 'New invoice '.$inv->invoice_no,
                    $inv->title.': '.money($inv->amount).'. Outstanding: '.money($inv->outstanding).'.', route('member.invoices'));
                if ($sendSms) {
                    $messages[] = [$inv->member->phone, SmsService::render('sms_tpl_invoice', [
                        'name' => $inv->member->full_name, 'invoice_no' => $inv->invoice_no, 'title' => $inv->title,
                        'amount' => number_format((float) $inv->amount, 2), 'outstanding' => number_format($inv->outstanding, 2),
                    ]), $inv->member];
                }
            }
            $this->sms->sendLater($messages, 'invoice');
        } catch (\Throwable $e) {
            report($e);
        }

        return count($ids);
    }

    /** Cancel an invoice and refund whatever was already paid on it. */
    public function cancel(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['reason' => 'This invoice is already cancelled.']);
            }
            $old = $invoice->status;
            $this->wallets->refundInvoice($invoice, $reason);
            $invoice->update([
                'status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancellation_reason' => $reason,
            ]);
            if ($invoice->welfareCase) {
                $invoice->welfareCase->update(['members_invoiced' => $invoice->welfareCase->invoices()->where('status', '!=', 'cancelled')->count()]);
                app(WelfareCaseService::class)->refreshStatus($invoice->welfareCase);
            }
            AuditLogger::log('invoice.cancelled', $invoice, ['status' => $old], ['status' => 'cancelled', 'reason' => $reason]);
        });
    }

    /** Pay an open invoice from the member's wallet on request. */
    public function payFromWallet(Invoice $invoice): bool
    {
        $tx = $this->wallets->applyToInvoice($invoice);
        if ($tx && $invoice->welfare_case_id) {
            WalletService::refreshCases([$invoice->welfare_case_id]);
        }

        return (bool) $tx;
    }
}
