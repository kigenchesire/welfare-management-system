<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SettingDefinitions;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe to run more than once: existing settings and accounts are never overwritten.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Permissions::defaults() as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($role->wasRecentlyCreated || $roleName === 'Administrator') {
                $role->syncPermissions($perms);
            }
        }

        foreach (SettingDefinitions::all() as $key => [$group, $type, $label, $default]) {
            Setting::firstOrCreate(['key' => $key], ['group' => $group, 'type' => $type, 'value' => $default]);
        }
        Settings::flush();

        // CHANGE THIS PASSWORD BEFORE GOING LIVE.
        $admin = User::firstOrCreate(['username' => 'admin@example.com'], [
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => User::DEFAULT_PASSWORD,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $admin->assignRole('Administrator');

        $this->command?->warn('Default admin: admin@example.com / '.User::DEFAULT_PASSWORD.' - change it before production use.');
    }
}
