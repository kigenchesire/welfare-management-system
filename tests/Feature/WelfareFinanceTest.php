<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WelfareCase;
use App\Support\Settings;
use Tests\TestCase;

class WelfareFinanceTest extends TestCase
{
    private function deposit(Member $m, float $amount, string $ref): void
    {
        $this->actingAs($this->staff('Treasurer'))->post(route('payments.store'), [
            'member_id' => $m->id, 'amount' => $amount, 'reference' => $ref, 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    private function createCase(Member $affected, $deceased = 'self')
    {
        return $this->actingAs($this->staff('Secretary'))->post(route('cases.store'), [
            'member_id' => $affected->id, 'deceased' => $deceased, 'bereavement_date' => now()->toDateString(), 'confirm' => '1',
        ]);
    }

    /** Every wallet balance must equal the sum of its ledger entries. */
    private function assertLedgerConsistent(): void
    {
        foreach (Wallet::all() as $w) {
            $credits = (float) WalletTransaction::where('wallet_id', $w->id)->where('direction', 'credit')->sum('amount');
            $debits = (float) WalletTransaction::where('wallet_id', $w->id)->where('direction', 'debit')->sum('amount');
            $this->assertEqualsWithDelta($credits - $debits, (float) $w->balance, 0.001, 'Ledger mismatch for wallet '.$w->id);
            $last = WalletTransaction::where('wallet_id', $w->id)->latest('id')->first();
            if ($last) {
                $this->assertEqualsWithDelta((float) $last->balance_after, (float) $w->balance, 0.001);
            }
        }
    }

    public function test_case_uses_configured_amount_and_deducts_full_partial_and_zero_balances(): void
    {
        $affected = $this->activeMember('Affected', [['name' => 'Mum', 'category' => 'parent']]);
        $rich = $this->activeMember('Rich');
        $partial = $this->activeMember('Partial');
        $broke = $this->activeMember('Broke');
        $pending = Member::create(['full_name' => 'Pending', 'national_id' => '99999991', 'phone' => '0700', 'registration_date' => now(), 'status' => 'pending']);

        $this->deposit($rich, 2000, 'RICH1');
        $this->deposit($partial, 250, 'PART1');

        $mum = $affected->beneficiaries()->first();
        $this->createCase($affected, (string) $mum->id)->assertSessionHasNoErrors();

        $case = WelfareCase::firstOrFail();
        $this->assertSame('parent', $case->category);
        $this->assertEquals(600, (float) $case->contribution_amount); // default parent rate
        $this->assertTrue($mum->fresh()->is_deceased);

        // Every approved member is invoiced, including the bereaved member (only a deceased member is not).
        $this->assertSame(4, $case->invoices()->count());
        $this->assertNull(Invoice::where('member_id', $pending->id)->first());
        $this->assertSame('unpaid', Invoice::where('member_id', $affected->id)->first()->status);

        $this->assertSame('paid', Invoice::where('member_id', $rich->id)->first()->status);
        $this->assertEquals(1400, (float) $rich->wallet->fresh()->balance);

        $p = Invoice::where('member_id', $partial->id)->first();
        $this->assertSame('partially_paid', $p->status);
        $this->assertEquals(250, (float) $p->amount_paid);
        $this->assertEquals(350, $p->outstanding);
        $this->assertEquals(0, (float) $partial->wallet->fresh()->balance);

        $b = Invoice::where('member_id', $broke->id)->first();
        $this->assertSame('unpaid', $b->status);
        $this->assertEquals(600, $b->outstanding);
        $this->assertEquals(0, (float) $broke->wallet->fresh()->balance);

        $this->assertSame('in_progress', $case->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.created', 'auditable_id' => $case->id]);
        $this->assertLedgerConsistent();
    }

    public function test_changing_settings_changes_new_case_amount(): void
    {
        Settings::set('contribution_self', '1500');
        $affected = $this->activeMember();
        $this->activeMember();
        $this->createCase($affected)->assertSessionHasNoErrors();
        $this->assertEquals(1500, (float) WelfareCase::first()->contribution_amount);
        $this->assertEquals(1500, (float) Invoice::first()->amount);
        $this->assertSame('inactive', $affected->fresh()->status); // member is deceased
        $this->assertNull(Invoice::where('member_id', $affected->id)->first()); // a deceased member is not invoiced
    }

    public function test_reprocessing_and_double_submission_do_not_duplicate_invoices(): void
    {
        $affected = $this->activeMember();
        $this->activeMember();
        $this->activeMember();
        $secretary = $this->staff('Secretary');

        $payload = ['member_id' => $affected->id, 'deceased' => 'self', 'bereavement_date' => now()->toDateString(), 'confirm' => '1', '_submission' => 'token-abc'];
        $this->actingAs($secretary)->post(route('cases.store'), $payload);
        $this->actingAs($secretary)->post(route('cases.store'), $payload)->assertSessionHas('error');
        $this->assertSame(1, WelfareCase::count());

        // A second case for the same bereavement is refused.
        $this->actingAs($secretary)->post(route('cases.store'), array_merge($payload, ['_submission' => 'token-xyz']))->assertSessionHasErrors();
        $this->assertSame(1, WelfareCase::count());

        $case = WelfareCase::first();
        $this->actingAs($secretary)->post(route('cases.process', $case));
        $this->actingAs($secretary)->post(route('cases.process', $case));
        $this->assertSame(2, $case->invoices()->count());

        // A member who joins later can be added without duplicating anyone else.
        $this->activeMember();
        $this->actingAs($secretary)->post(route('cases.process', $case));
        $this->assertSame(3, $case->invoices()->count());
        $this->assertSame(3, $case->invoices()->distinct('member_id')->count('member_id'));
    }

    public function test_deposit_settles_outstanding_and_rejects_duplicate_reference(): void
    {
        $affected = $this->activeMember();
        $m = $this->activeMember();
        $this->createCase($affected);
        $invoice = Invoice::where('member_id', $m->id)->first();
        $this->assertSame('unpaid', $invoice->status);

        $this->deposit($m, 1500, 'QWE123');
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(400, (float) $m->wallet->fresh()->balance); // 1500 - 1100
        $this->assertSame('fully_collected', WelfareCase::first()->status);

        // Same Paybill code again (any case) is refused.
        $this->actingAs($this->staff('Treasurer'))->post(route('payments.store'), [
            'member_id' => $m->id, 'amount' => 1500, 'reference' => 'qwe123', 'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('reference');
        $this->assertSame(1, Payment::count());
        $this->assertLedgerConsistent();
    }

    public function test_cancelling_case_refunds_deductions_and_cancels_invoices(): void
    {
        $affected = $this->activeMember();
        $m = $this->activeMember();
        $n = $this->activeMember();
        $this->deposit($m, 1100, 'AAA1');
        $this->createCase($affected);
        $this->assertEquals(0, (float) $m->wallet->fresh()->balance);

        $case = WelfareCase::first();
        $this->actingAs($this->staff('Secretary'))->post(route('cases.cancel', $case), ['reason' => 'Reported in error'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $case->fresh()->status);
        $this->assertSame(0, $case->invoices()->open()->count());
        $this->assertEquals(1100, (float) $m->wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('member_id', $m->id)->where('type', 'refund')->count());
        $this->assertSame(0, WalletTransaction::where('member_id', $n->id)->where('type', 'refund')->count());
        $this->assertSame('active', $affected->fresh()->status);
        $this->assertLedgerConsistent();
    }

    public function test_no_negative_balance_without_authorisation_and_ledger_is_append_only(): void
    {
        $m = $this->activeMember();
        $treasurer = $this->staff('Treasurer');
        $this->actingAs($treasurer)->post(route('wallets.adjust', $m), ['direction' => 'debit', 'amount' => 100, 'reason' => 'Test'])->assertSessionHas('error');
        $this->assertEquals(0, (float) $m->wallet->fresh()->balance);

        $this->actingAs($treasurer)->post(route('wallets.adjust', $m), ['direction' => 'debit', 'amount' => 100, 'reason' => 'Approved', 'authorize_negative' => '1'])->assertSessionHas('success');
        $this->assertEquals(-100, (float) $m->wallet->fresh()->balance);
        $this->assertLedgerConsistent();

        $this->expectException(\LogicException::class);
        WalletTransaction::first()->delete();
    }

    public function test_payment_reversal_posts_reversal_entry(): void
    {
        $m = $this->activeMember();
        $this->deposit($m, 700, 'REV1');
        $payment = Payment::first();
        $this->actingAs($this->staff('Treasurer'))->post(route('payments.reverse', $payment), ['reason' => 'Wrong member'])->assertSessionHasNoErrors();
        $this->assertSame('reversed', $payment->fresh()->status);
        $this->assertEquals(0, (float) $m->wallet->fresh()->balance);
        $this->assertSame(2, WalletTransaction::where('payment_id', $payment->id)->count());
        $this->assertLedgerConsistent();
    }

    public function test_custom_invoice_and_cancellation_refund(): void
    {
        $m = $this->activeMember();
        $n = $this->activeMember();
        $this->deposit($m, 300, 'CUS1');
        $this->actingAs($this->staff('Treasurer'))->post(route('invoices.store'), [
            'title' => 'Airtime November', 'category' => 'airtime', 'amount' => 200, 'recipients' => 'all', 'deduct' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Invoice::where('type', 'custom')->count());
        $inv = Invoice::where('member_id', $m->id)->where('type', 'custom')->first();
        $this->assertSame('paid', $inv->status);
        $this->assertSame('unpaid', Invoice::where('member_id', $n->id)->where('type', 'custom')->first()->status);

        $this->actingAs($this->staff('Treasurer'))->post(route('invoices.cancel', $inv), ['reason' => 'Error'])->assertSessionHasNoErrors();
        $this->assertEquals(300, (float) $m->wallet->fresh()->balance);
        $this->assertLedgerConsistent();
    }

    public function test_role_permissions_are_enforced(): void
    {
        $affected = $this->activeMember();
        $treasurer = $this->staff('Treasurer');
        $secretary = $this->staff('Secretary');
        $chair = $this->staff('Chairperson');

        $this->actingAs($treasurer)->post(route('cases.store'), ['member_id' => $affected->id, 'deceased' => 'self', 'bereavement_date' => now()->toDateString(), 'confirm' => 1])->assertForbidden();
        $this->actingAs($secretary)->post(route('payments.store'), ['member_id' => $affected->id, 'amount' => 10, 'reference' => 'X1', 'payment_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($chair)->get(route('finance.summary'))->assertOk();
        $this->actingAs($chair)->get(route('cases.create'))->assertForbidden();
        $this->actingAs($chair)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($affected->user)->get(route('members.index'))->assertForbidden();
        $this->actingAs($affected->user)->get(route('reports.show', 'financial-summary'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.settings'))->assertOk();
    }

    public function test_finance_summary_separates_wallet_liability_from_collections(): void
    {
        $affected = $this->activeMember();
        $m = $this->activeMember();
        $this->deposit($m, 5000, 'FIN1');
        $this->createCase($affected);

        $s = app(\App\Services\FinanceService::class)->summary();
        $this->assertEquals(5000, $s['deposits']);
        $this->assertEquals(1100, $s['welfare_collected']);
        $this->assertEquals(3900, $s['wallet_balances']);
    }

    public function test_transaction_cost_is_optional_and_not_credited(): void
    {
        $m = $this->activeMember();
        $this->deposit($m, 500, 'NOCOST1');
        $this->assertNull(Payment::where('reference', 'NOCOST1')->first()->transaction_cost);

        $this->actingAs($this->staff('Treasurer'))->post(route('payments.store'), [
            'member_id' => $m->id, 'amount' => 1000, 'transaction_cost' => 23, 'reference' => 'COST1', 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(23, (float) Payment::where('reference', 'COST1')->first()->transaction_cost);
        $this->assertEquals(1500, (float) $m->wallet->fresh()->balance);
        $this->assertLedgerConsistent();
    }

    public function test_case_payment_goes_to_that_case_first_and_can_be_partial(): void
    {
        Settings::set('auto_apply_deposits', '0');
        $a = $this->activeMember('A', [['name' => 'Kid A', 'category' => 'child']]);
        $b = $this->activeMember('B', [['name' => 'Dad B', 'category' => 'parent']]);
        $m = $this->activeMember('Payer');
        $this->createCase($a, (string) $a->beneficiaries()->first()->id); // older case, 1100
        $this->createCase($b, (string) $b->beneficiaries()->first()->id); // newer case, 600
        [$older, $newer] = WelfareCase::orderBy('id')->get()->all();
        $treasurer = $this->staff('Treasurer');

        // Part payment on the newer case; the older invoice is not touched.
        $this->actingAs($treasurer)->post(route('cases.payments.store', $newer), [
            'member_id' => $m->id, 'amount' => 400, 'reference' => 'CASEP1', 'transaction_cost' => 7, 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('cases.show', $newer));
        $inv = Invoice::where('welfare_case_id', $newer->id)->where('member_id', $m->id)->first();
        $this->assertSame('partially_paid', $inv->status);
        $this->assertEquals(200, $inv->outstanding);
        $this->assertSame('unpaid', Invoice::where('welfare_case_id', $older->id)->where('member_id', $m->id)->first()->status);
        $p = Payment::where('reference', 'CASEP1')->first();
        $this->assertSame($newer->id, $p->welfare_case_id);
        $this->assertEquals(7, (float) $p->transaction_cost);

        // Paying more than owed clears the case; the extra stays in the wallet.
        $this->actingAs($treasurer)->post(route('cases.payments.store', $newer), [
            'member_id' => $m->id, 'amount' => 500, 'reference' => 'CASEP2', 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertEquals(300, (float) $m->wallet->fresh()->balance);

        // Only members invoiced on the case, and only roles that record deposits.
        $outsider = Member::create(['full_name' => 'Pending X', 'national_id' => '99999992', 'phone' => '0700', 'registration_date' => now(), 'status' => 'pending']);
        $this->actingAs($treasurer)->post(route('cases.payments.store', $newer), [
            'member_id' => $outsider->id, 'amount' => 100, 'reference' => 'CASEP3', 'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('member_id');
        $this->actingAs($this->staff('Secretary'))->post(route('cases.payments.store', $newer), [
            'member_id' => $m->id, 'amount' => 100, 'reference' => 'CASEP4', 'payment_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->assertLedgerConsistent();
    }

    public function test_case_reminders_sms_each_unpaid_member_their_amount(): void
    {
        $affected = $this->activeMember('Affected', [['name' => 'Mum', 'category' => 'parent']]);
        $paid = $this->activeMember('Paid');
        $part = $this->activeMember('Part');
        $this->deposit($paid, 600, 'RM1');
        $this->deposit($part, 100, 'RM2');
        $this->createCase($affected, (string) $affected->beneficiaries()->first()->id);
        $case = WelfareCase::first();

        $this->actingAs($this->staff('Secretary'))->get(route('cases.show', $case))->assertOk()->assertSee('Remind unpaid (2)');
        // SMS go out after the response (the test client runs those callbacks at the end of the request).
        $this->actingAs($this->staff('Secretary'))->post(route('cases.remind', $case))->assertSessionHas('success');

        $logs = \App\Models\SmsLog::where('category', 'reminder')->get();
        $this->assertCount(2, $logs);
        $this->assertStringContainsString('500.00', $logs->firstWhere('member_id', $part->id)->message);
        $this->assertStringContainsString('600.00', $logs->firstWhere('member_id', $affected->id)->message);
        $this->assertNull($logs->firstWhere('member_id', $paid->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.reminders_sent', 'auditable_id' => $case->id]);

        // A single member can also be reminded on their own.
        $inv = Invoice::where('member_id', $part->id)->first();
        $this->actingAs($this->staff('Treasurer'));
        $this->assertSame(1, app(\App\Services\WelfareCaseService::class)->remindUnpaid($case, [$inv->id]));

        $this->actingAs($affected->user)->post(route('cases.remind', $case))->assertForbidden();
    }
}
