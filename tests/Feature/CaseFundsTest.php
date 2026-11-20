<?php

namespace Tests\Feature;

use App\Models\CaseDisbursement;
use App\Models\Member;
use App\Models\OfficeTransaction;
use App\Models\WelfareCase;
use App\Services\OfficeService;
use App\Support\Settings;
use Tests\TestCase;

class CaseFundsTest extends TestCase
{
    private function deposit(Member $m, float $amount, string $ref): void
    {
        $this->actingAs($this->staff('Treasurer'))->post(route('payments.store'), [
            'member_id' => $m->id, 'amount' => $amount, 'reference' => $ref, 'payment_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    /** Parent case (600): three payers with 600, 120 and nothing. */
    private function parentCase(): WelfareCase
    {
        $affected = $this->activeMember('Affected', [['name' => 'Mum', 'category' => 'parent']]);
        $full = $this->activeMember('Full');
        $part = $this->activeMember('Part');
        $this->deposit($full, 600, 'F1');
        $this->deposit($part, 120, 'P1');
        $this->actingAs($this->staff('Secretary'))->post(route('cases.store'), [
            'member_id' => $affected->id, 'deceased' => (string) $affected->beneficiaries()->first()->id,
            'bereavement_date' => now()->toDateString(), 'confirm' => '1',
        ])->assertSessionHasNoErrors();

        return WelfareCase::firstOrFail();
    }

    public function test_office_and_transport_are_taken_first_from_each_payment(): void
    {
        $this->assertSame(['office' => 100.0, 'transport' => 50.0, 'welfare' => 450.0], WelfareCase::split(600, 100, 50));
        $this->assertSame(['office' => 100.0, 'transport' => 20.0, 'welfare' => 0.0], WelfareCase::split(120, 100, 50));
        $this->assertSame(['office' => 60.0, 'transport' => 0.0, 'welfare' => 0.0], WelfareCase::split(60, 100, 50));

        $case = $this->parentCase();
        $this->assertEquals(100, (float) $case->office_share);
        $this->assertEquals(50, (float) $case->transport_share);

        $f = $case->funds();
        $this->assertEquals(200, $f['office']);     // 100 + 100
        $this->assertEquals(70, $f['transport']);   // 50 + 20
        $this->assertEquals(450, $f['welfare']);    // 450 + 0
        $this->assertEquals(720, $f['office'] + $f['transport'] + $f['welfare']);
        $this->assertEquals(300, $f['office_due']); // 3 invoices (affected member included)
        $this->assertEquals(1350, $f['welfare_due']);

        $this->actingAs($this->admin())->get(route('cases.show', $case))->assertOk()
            ->assertSee('Where the money goes')->assertSee('450.00')->assertSee('Record disbursement');
    }

    public function test_changing_shares_only_affects_new_cases(): void
    {
        $case = $this->parentCase();
        Settings::set('office_share_per_case', '200');
        Settings::set('transport_share_per_case', '0');
        $this->assertEquals(200, $case->fresh()->funds()['office']);

        $other = $this->activeMember('Other', [['name' => 'Kid', 'category' => 'child']]);
        $this->actingAs($this->staff('Secretary'))->post(route('cases.store'), [
            'member_id' => $other->id, 'deceased' => (string) $other->beneficiaries()->first()->id,
            'bereavement_date' => now()->toDateString(), 'confirm' => '1',
        ])->assertSessionHasNoErrors();
        $new = WelfareCase::latest('id')->first();
        $this->assertEquals(200, (float) $new->office_share);
        $this->assertEquals(0, (float) $new->transport_share);
    }

    public function test_disbursements_are_limited_to_collected_money_and_can_be_voided(): void
    {
        $case = $this->parentCase();
        $treasurer = $this->staff('Treasurer');
        $base = ['recipient' => 'Jane Family', 'disbursement_date' => now()->toDateString(), 'remarks' => 'Burial support'];

        $this->actingAs($treasurer)->post(route('cases.disburse', $case), $base + ['fund' => 'welfare', 'amount' => 451])->assertSessionHasErrors('amount');
        $this->actingAs($treasurer)->post(route('cases.disburse', $case), $base + ['fund' => 'welfare', 'amount' => 400])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('cases.disburse', $case), $base + ['fund' => 'transport', 'amount' => 70, 'recipient' => 'Bus hire'])->assertSessionHasNoErrors();

        $f = $case->funds();
        $this->assertEquals(50, $f['welfare_available']);
        $this->assertEquals(0, $f['transport_available']);
        $d = CaseDisbursement::where('fund', 'welfare')->first();
        $this->assertSame('DSB'.str_pad((string) $d->id, 6, '0', STR_PAD_LEFT), $d->reference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.disbursed', 'auditable_id' => $d->id]);

        // A case with money paid out cannot be cancelled until the disbursements are voided.
        $secretary = $this->staff('Secretary');
        $this->actingAs($secretary)->post(route('cases.cancel', $case), ['reason' => 'Error'])->assertSessionHasErrors('reason');

        $this->actingAs($treasurer)->post(route('disbursements.void', $d), ['reason' => 'Wrong amount'])->assertSessionHasNoErrors();
        $this->assertSame('voided', $d->fresh()->status);
        $this->assertEquals(450, $case->funds()['welfare_available']);

        // Only roles with the permission.
        $this->actingAs($secretary)->post(route('cases.disburse', $case), $base + ['fund' => 'welfare', 'amount' => 10])->assertForbidden();
        $this->actingAs($case->member->user)->post(route('disbursements.void', $d), ['reason' => 'x'])->assertForbidden();
    }

    public function test_office_account_income_topups_expenses_and_reversals(): void
    {
        $this->parentCase(); // 200 office income
        $office = app(OfficeService::class);
        $this->assertEquals(200, $office->balance());

        $treasurer = $this->staff('Treasurer');
        $this->actingAs($treasurer)->post(route('office.topup'), [
            'amount' => 1000, 'party' => 'Harambee', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1200, $office->balance());

        $this->actingAs($treasurer)->post(route('office.expense'), [
            'amount' => 1500, 'description' => 'Too big', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
        $this->actingAs($treasurer)->post(route('office.expense'), [
            'amount' => 300, 'description' => 'Stationery', 'category' => 'Stationery', 'party' => 'Bookshop', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(900, $office->balance());

        $expense = OfficeTransaction::where('type', 'expense')->first();
        $this->actingAs($treasurer)->post(route('office.reverse', $expense), ['reason' => 'Duplicate'])->assertSessionHasNoErrors();
        $this->assertEquals(1200, $office->balance());
        $this->actingAs($treasurer)->post(route('office.reverse', $expense), ['reason' => 'Again'])->assertSessionHasErrors('reason');

        $st = $office->statement();
        $this->assertEquals($office->balance(), $st['closing']);
        $this->assertSame('case_income', $st['rows'][0]['type']);

        $this->actingAs($treasurer)->get(route('office.index'))->assertOk()->assertSee('Harambee')->assertSee('Stationery');
        $this->actingAs($this->staff('Chairperson'))->get(route('office.index'))->assertOk();
        $this->actingAs($this->staff('Chairperson'))->post(route('office.topup'), ['amount' => 5, 'party' => 'x', 'transaction_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($this->staff('Secretary'))->get(route('office.index'))->assertForbidden();

        $this->expectException(\LogicException::class);
        $expense->delete();
    }

    public function test_cancelled_case_removes_its_office_income(): void
    {
        $case = $this->parentCase();
        $office = app(OfficeService::class);
        $this->assertEquals(200, $office->balance());

        $this->actingAs($this->staff('Secretary'))->post(route('cases.cancel', $case), ['reason' => 'Reported in error'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $office->balance());
        $st = $office->statement();
        $this->assertEquals(0, $st['closing']);
        $this->assertTrue(collect($st['rows'])->contains(fn ($r) => $r['out'] == 200 && $r['type'] === 'case_income'));
    }
}
