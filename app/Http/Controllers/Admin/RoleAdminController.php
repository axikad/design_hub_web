<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleAdminController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query->where('is_active', true)->where('action', '!=', 'legacy')])
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);
        $permissions = Permission::query()
            ->with('module')
            ->where('is_active', true)
            ->where('action', '!=', 'legacy')
            ->get()
            ->sortBy(fn (Permission $permission) => sprintf(
                '%010d|%s|%010d',
                $permission->module?->sort_order ?? 0,
                $permission->module?->name ?? '',
                $permission->id,
            ))
            ->values();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'name' => $permission->name,
                'action' => $permission->action,
                'module' => $permission->module?->name,
            ]),
            'assignedPermissionIds' => $roles->mapWithKeys(fn (Role $role) => [
                $role->id => $role->permissions->pluck('id')->values(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $slug = Role::slugFromName($validated['name']);

        if (Role::query()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists.']);
        }

        $role = Role::query()->create([
            'name' => trim($validated['name']),
            'slug' => $slug,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        if ($role->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Super Admin permissions cannot be changed.']);
        }

        $validated = $request->validate([
            'permissionIds' => ['present', 'array'],
            'permissionIds.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('action', '!=', 'legacy'),
                ),
            ],
        ]);

        $syncData = collect($validated['permissionIds'])->mapWithKeys(fn (int $permissionId) => [
            $permissionId => ['assigned_by' => $request->user()?->id],
        ])->all();

        $role->permissions()->sync($syncData);

        return redirect()->route('admin.roles.index')->with('success', 'Permissions updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Super Admin role cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Remove users from this role before deleting it.']);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
