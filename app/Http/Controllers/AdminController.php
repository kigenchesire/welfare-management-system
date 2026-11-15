<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** User accounts, roles/permissions and the audit trail. */
class AdminController extends Controller
{
    // ---------- Users ----------

    public function users()
    {
        return view('admin.users.index', ['users' => User::with(['roles', 'member'])->orderBy('name')->get()]);
    }

    public function createUser()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function storeUser(Request $request)
    {
        $data = $this->validateUser($request);
        $user = User::create($data + ['password' => User::DEFAULT_PASSWORD, 'must_change_password' => true]);
        $user->syncRoles($data['roles']);
        AuditLogger::log('user.created', $user, null, $user->only(['name', 'username', 'email', 'phone']) + ['roles' => $data['roles']]);

        return redirect()->route('admin.users')->with('success', 'User created. Login: '.$user->username.' / '.User::DEFAULT_PASSWORD);
    }

    public function editUser(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);
        $this->guardLastAdmin($user, $data['roles'], (bool) $data['is_active']);

        $oldRoles = $user->getRoleNames()->all();
        $user->fill($data);
        AuditLogger::logChanges('user.updated', $user);
        $user->save();
        $user->syncRoles($data['roles']);
        if ($oldRoles != $data['roles']) {
            AuditLogger::log('user.roles_changed', $user, ['roles' => $oldRoles], ['roles' => $data['roles']]);
        }

        return redirect()->route('admin.users')->with('success', 'User updated.');
    }

    public function resetUserPassword(User $user)
    {
        $user->forceFill(['password' => User::DEFAULT_PASSWORD, 'must_change_password' => true])->save();
        AuditLogger::log('user.password_reset', $user);

        return back()->with('success', 'Password for '.$user->name.' reset to '.User::DEFAULT_PASSWORD.'.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email:filter', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Never allow the system to end up with no active administrator. */
    private function guardLastAdmin(User $user, array $roles, bool $active): void
    {
        if ($user->hasRole('Administrator') && (! in_array('Administrator', $roles, true) || ! $active)) {
            $others = User::role('Administrator')->where('is_active', true)->whereKeyNot($user->id)->count();
            abort_if($others === 0, 422, 'At least one active Administrator must remain.');
        }
    }

    // ---------- Roles & permissions ----------

    public function roles()
    {
        return view('admin.roles', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('id')->get(),
            'groups' => Permissions::ALL,
        ]);
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_if($role->name === 'Administrator', 422, 'The Administrator role always has every permission.');
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::names())],
        ]);
        $old = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLogger::log('role.permissions_changed', $role, ['permissions' => $old], ['permissions' => $data['permissions'] ?? []]);

        return back()->with('success', 'Permissions for '.$role->name.' updated.');
    }

    // ---------- Audit logs (read only) ----------

    public function audit(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->input('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->input('user'), fn ($q, $u) => $q->where('user_name', 'like', "%{$u}%"))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'actions' => AuditLog::query()->selectRaw("DISTINCT SUBSTRING_INDEX(action, '.', 1) a")->pluck('a'),
        ]);
    }

    public function auditShow(AuditLog $log)
    {
        return view('admin.audit-show', compact('log'));
    }
}
