<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WelfareCase;
use App\Services\ReportService;
use Tests\TestCase;

/** Smoke test: every page renders for the roles allowed to see it. */
class PagesTest extends TestCase
{
    private function seedActivity(): array
    {
        $affected = $this->activeMember('Affected', [['name' => 'Kid', 'category' => 'child']]);
        $m = $this->activeMember('Payer');
        $this->actingAs($this->admin())->post(route('payments.store'), [
            'member_id' => $m->id, 'amount' => 3000, 'reference' => 'PG1', 'payment_date' => now()->toDateString(),
        ]);
        $this->actingAs($this->admin())->post(route('cases.store'), [
            'member_id' => $affected->id, 'deceased' => (string) $affected->beneficiaries()->first()->id,
            'bereavement_date' => now()->toDateString(), 'confirm' => '1',
        ]);

        return [$affected, $m];
    }

    public function test_admin_pages_render(): void
    {
        [$affected, $m] = $this->seedActivity();
        $admin = $this->admin();
        $case = WelfareCase::first();
        $invoice = Invoice::first();
        $payment = Payment::first();

        $urls = [
            route('dashboard'), route('members.index'), route('members.pending'), route('members.create'),
            route('members.show', $m), route('members.edit', $m), route('beneficiaries.index'),
            route('cases.index'), route('cases.create'), route('cases.show', $case),
            route('invoices.index'), route('invoices.show', $invoice), route('invoices.create'),
            route('wallets.index'), route('wallets.show', $m), route('transactions.index'),
            route('payments.index'), route('payments.create'), route('payments.show', $payment),
            route('finance.summary'), route('reports.index'), route('announcements.index'),
            route('sms.logs'), route('notifications'), route('admin.users'), route('admin.users.create'),
            route('admin.users.edit', $admin), route('admin.roles'), route('admin.audit'),
            route('admin.settings'), route('admin.settings', ['tab' => 'financial']), route('admin.settings', ['tab' => 'sms']),
            route('admin.website'), route('password.change'), route('members.search', ['q' => 'Pay']),
            route('members.beneficiaries-json', $affected),
        ];
        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.audit.show', \App\Models\AuditLog::first()))->assertOk();
        $this->actingAs($admin)->get(route('payments.show', [$payment, 'pdf' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_every_report_renders_and_exports(): void
    {
        $this->seedActivity();
        $admin = $this->admin();
        $caseId = WelfareCase::first()->id;
        foreach (array_keys(ReportService::REPORTS) as $key) {
            $params = ['case_id' => $caseId, 'from' => now()->subYear()->toDateString(), 'to' => now()->toDateString()];
            $this->actingAs($admin)->get(route('reports.show', [$key] + $params))->assertOk();
            $this->actingAs($admin)->get(route('reports.show', [$key] + $params + ['export' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->actingAs($admin)->get(route('reports.show', [$key] + $params + ['export' => 'excel']))->assertOk();
        }
    }

    public function test_member_portal_pages_render(): void
    {
        [, $m] = $this->seedActivity();
        $user = $m->user;
        $invoice = $m->invoices()->first();
        $payment = $m->payments()->first();
        foreach ([route('dashboard'), route('member.profile'), route('member.beneficiaries'), route('member.wallet'),
                     route('member.invoices'), route('member.invoice', $invoice->id), route('member.cases'),
                     route('member.payments'), route('member.receipt', $payment->id), route('notifications')] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_settings_update_shows_on_login_page(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'group' => 'general', 'group_name' => 'Tumaini Welfare', 'short_name' => 'TW', 'primary_color' => '#123456',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('Tumaini Welfare')->assertSee('#123456');
    }
}
