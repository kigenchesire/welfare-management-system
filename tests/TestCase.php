<?php

namespace Tests;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    protected function admin(): User
    {
        return User::where('username', 'admin@example.com')->firstOrFail();
    }

    protected function staff(string $role): User
    {
        $u = User::create([
            'name' => 'Test '.$role, 'username' => strtolower($role).uniqid(), 'password' => User::DEFAULT_PASSWORD, 'is_active' => true,
        ]);
        $u->assignRole($role);

        return $u;
    }

    /** Register and approve a member, returning the active Member. */
    protected function activeMember(string $name = null, array $beneficiaries = []): Member
    {
        $previous = Auth::user();
        Auth::login($this->admin());
        $service = app(MemberService::class);
        $m = $service->register([
            'full_name' => $name ?? 'Member '.uniqid(),
            'national_id' => (string) random_int(10000000, 99999999),
            'phone' => '07'.random_int(10000000, 99999999),
        ], $beneficiaries, 'office');
        $service->approve($m);
        $previous ? Auth::login($previous) : Auth::logout();

        return $m->fresh(['wallet', 'user']);
    }
}
