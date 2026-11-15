<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Member;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    /** Skip public-website tests while the homepage is switched off in routes/web.php. */
    private function requirePublicWebsite(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('register')) {
            $this->markTestSkipped('Public website and online registration are disabled.');
        }
    }

    public function test_root_lands_on_login_page_with_configurable_name(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Luxurious Welfare Group');

        Settings::set('group_name', 'Umoja Welfare Society');
        $this->get('/login')->assertOk()
            ->assertSee('Umoja Welfare Society')
            ->assertDontSee('Luxurious Welfare Group');
    }

    public function test_public_registration_creates_pending_member_and_notifies_secretary(): void
    {
        $this->requirePublicWebsite();
        $secretary = $this->staff('Secretary');

        $this->post('/register', [
            'full_name' => 'Alice Wambui',
            'national_id' => '12345678',
            'phone' => '0712345678',
            'agree' => '1',
            'beneficiaries' => [
                ['name' => 'Mother Wambui', 'category' => 'parent'],
                ['name' => '', 'category' => 'parent'],
                ['name' => 'Kid Wambui', 'category' => 'child'],
            ],
        ])->assertRedirect();

        $member = Member::where('national_id', '12345678')->firstOrFail();
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->user_id);
        $this->assertSame(2, $member->beneficiaries()->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $secretary->id, 'type' => 'registration']);
    }

    public function test_duplicate_national_id_is_rejected(): void
    {
        $this->requirePublicWebsite();
        $existing = $this->activeMember();
        $this->post('/register', [
            'full_name' => 'Someone Else', 'national_id' => $existing->national_id, 'phone' => '0700000001', 'agree' => '1',
        ])->assertSessionHasErrors('national_id');
        $this->assertSame(1, Member::where('national_id', $existing->national_id)->count());
    }

    public function test_beneficiary_limits_on_registration(): void
    {
        $this->requirePublicWebsite();
        $this->post('/register', [
            'full_name' => 'Too Many', 'national_id' => '55555555', 'phone' => '0700000002', 'agree' => '1',
            'beneficiaries' => [
                ['name' => 'S1', 'category' => 'spouse'],
                ['name' => 'S2', 'category' => 'spouse'],
            ],
        ])->assertSessionHasErrors('beneficiaries');
        $this->assertDatabaseMissing('members', ['national_id' => '55555555']);
    }

    public function test_secretary_approval_creates_login_with_default_password(): void
    {
        $secretary = $this->staff('Secretary');
        $member = Member::create([
            'full_name' => 'Bob Kariuki', 'national_id' => '87654321', 'phone' => '0722000000',
            'registration_date' => now(), 'status' => 'pending',
        ]);

        $this->actingAs($secretary)->post(route('members.approve', $member))->assertRedirect();

        $member->refresh();
        $this->assertSame('active', $member->status);
        $this->assertNotNull($member->member_no);
        $this->assertNotNull($member->wallet);
        $this->assertTrue(Hash::check('123456', $member->user->password));
        $this->assertTrue($member->user->hasRole('Member'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.approved', 'auditable_id' => $member->id]);
        $this->assertDatabaseHas('sms_logs', ['member_id' => $member->id, 'category' => 'credentials']);

        // The member can now sign in with National ID or phone.
        auth()->logout();
        $this->post('/login', ['login' => '87654321', 'password' => '123456'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($member->user);
    }

    public function test_password_rules_allow_any_characters_up_to_six(): void
    {
        $member = $this->activeMember();
        $this->actingAs($member->user)->put(route('password.update'), [
            'current_password' => '123456', 'password' => 'abcdefg', 'password_confirmation' => 'abcdefg',
        ])->assertSessionHasErrors('password');

        $this->actingAs($member->user)->put(route('password.update'), [
            'current_password' => '123456', 'password' => 'a!', 'password_confirmation' => 'a!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('a!', $member->user->fresh()->password));
    }

    public function test_only_secretary_can_manage_beneficiaries(): void
    {
        $member = $this->activeMember(null, [['name' => 'Dad', 'category' => 'parent']]);
        $beneficiary = $member->beneficiaries()->first();

        // Members and treasurers are refused on the server, not just hidden in the menu.
        $this->actingAs($member->user)->post(route('beneficiaries.store', $member), ['name' => 'X', 'category' => 'child'])->assertForbidden();
        $this->actingAs($member->user)->put(route('beneficiaries.update', $beneficiary), ['name' => 'X', 'category' => 'parent'])->assertForbidden();
        $this->actingAs($this->staff('Treasurer'))->delete(route('beneficiaries.destroy', $beneficiary))->assertForbidden();

        $secretary = $this->staff('Secretary');
        $this->actingAs($secretary)->put(route('beneficiaries.update', $beneficiary), ['name' => 'Father Renamed', 'category' => 'parent'])->assertRedirect();
        $this->assertSame('Father Renamed', $beneficiary->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'beneficiary.updated', 'auditable_id' => $beneficiary->id]);

        // Spouse limit of one.
        $this->actingAs($secretary)->post(route('beneficiaries.store', $member), ['name' => 'Wife', 'category' => 'spouse'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('beneficiaries.store', $member), ['name' => 'Second', 'category' => 'spouse'])->assertSessionHasErrors('category');
        $this->assertSame(1, $member->beneficiaries()->where('category', 'spouse')->count());
    }

    public function test_member_cannot_see_other_members_records(): void
    {
        $a = $this->activeMember();
        $b = $this->activeMember();
        $this->actingAs($this->admin())->post(route('payments.store'), [
            'member_id' => $b->id, 'amount' => 500, 'reference' => 'REFB001', 'payment_date' => now()->toDateString(),
        ]);
        $paymentB = $b->payments()->first();

        $this->actingAs($a->user)->get(route('member.receipt', $paymentB->id))->assertNotFound();
        $this->actingAs($a->user)->get(route('members.show', $b))->assertForbidden();
        $this->actingAs($a->user)->get(route('wallets.show', $b))->assertForbidden();
        $this->actingAs($b->user)->get(route('member.receipt', $paymentB->id))->assertOk();
    }

    public function test_suspended_member_is_signed_out(): void
    {
        $member = $this->activeMember();
        $this->actingAs($this->staff('Secretary'))->post(route('members.status', $member), ['status' => 'suspended'])->assertRedirect();
        $this->assertFalse($member->user->fresh()->is_active);
        $this->actingAs($member->user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_audit_logs_cannot_be_modified(): void
    {
        $log = AuditLog::create(['action' => 'test', 'created_at' => now()]);
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'changed']);
    }
}
