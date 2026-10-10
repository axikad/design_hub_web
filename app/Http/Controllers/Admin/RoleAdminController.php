<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleAdminController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'slug', 'name']);
        $permissions = Permission::query()->with('module')->orderBy('module_id')->orderBy('name')->get();

        $assignments = RolePermission::query()->get()->groupBy('role_id');

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'name' => $permission->name,
                'module' => $permission->module?->name,
            ]),
            'matrix' => $roles->mapWithKeys(function (Role $role) use ($assignments) {
                $rows = $assignments->get($role->id, collect());

                return [
                    $role->id => $rows->mapWithKeys(fn (RolePermission $row) => [
                        $row->permission_id => [
                            'read' => $row->read,
                            'create' => $row->create,
                            'update' => $row->update,
                            'delete' => $row->delete,
                        ],
                    ]),
                ];
            }),
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

        foreach (Permission::query()->pluck('id') as $permissionId) {
            RolePermission::query()->create([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        if ($role->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Super Admin permissions cannot be changed.']);
        }

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.permissionId' => ['required', 'integer', Rule::exists('permissions', 'id')],
            'assignments.*.read' => ['boolean'],
            'assignments.*.create' => ['boolean'],
            'assignments.*.update' => ['boolean'],
            'assignments.*.delete' => ['boolean'],
        ]);

        foreach ($validated['assignments'] as $assignment) {
            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $assignment['permissionId'],
                ],
                [
                    'read' => $assignment['read'] ?? false,
                    'create' => $assignment['create'] ?? false,
                    'update' => $assignment['update'] ?? false,
                    'delete' => $assignment['delete'] ?? false,
                ],
            );
        }

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
