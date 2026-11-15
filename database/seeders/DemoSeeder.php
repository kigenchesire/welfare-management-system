<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\MemberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Optional sample data for trying the system out. NOT for production.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Chairperson', 'Secretary', 'Treasurer'] as $role) {
            $u = User::firstOrCreate(['username' => strtolower($role).'@example.com'], [
                'name' => 'Demo '.$role, 'email' => strtolower($role).'@example.com',
                'password' => User::DEFAULT_PASSWORD, 'is_active' => true,
            ]);
            $u->assignRole($role);
        }

        Auth::login(User::where('username', 'secretary@example.com')->first());
        $service = app(MemberService::class);

        $people = [
            ['Mary Wanjiku', '20000001', '0711000001'], ['John Otieno', '20000002', '0711000002'],
            ['Grace Achieng', '20000003', '0711000003'], ['Peter Kamau', '20000004', '0711000004'],
            ['Faith Njeri', '20000005', '0711000005'], ['David Mwangi', '20000006', '0711000006'],
        ];
        foreach ($people as $i => [$name, $id, $phone]) {
            if (\App\Models\Member::where('national_id', $id)->exists()) {
                continue;
            }
            $m = $service->register(['full_name' => $name, 'national_id' => $id, 'phone' => $phone, 'residential_address' => 'Nairobi'], [
                ['name' => 'Parent of '.$name, 'category' => 'parent'],
                ['name' => 'Spouse of '.$name, 'category' => 'spouse'],
                ['name' => 'Child of '.$name, 'category' => 'child'],
            ], 'office');
            if ($i < 5) {
                $service->approve($m);
            }
        }
        Auth::logout();
    }
}
