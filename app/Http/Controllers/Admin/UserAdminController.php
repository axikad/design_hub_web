<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with('role')
            ->withCount(['designs as listed_designs_count' => fn ($query) => $query->listed()])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('role', fn ($query) => $query->where('slug', $role)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => array_merge($user->toAdminArray(), [
                'designsCount' => $user->listed_designs_count,
            ]));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => null,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $role = Role::query()->findOrFail($validated['roleId']);

        User::query()->create([
            'name' => $validated['name'],
            'role_id' => $role->id,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'country_code' => $validated['countryCode'],
            'mobile_number' => $validated['mobileNumber'],
            'phone_key' => $validated['phoneKey'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(string $user): Response
    {
        $model = User::query()->with('role')->where('public_id', $user)->firstOrFail();

        return Inertia::render('admin/users/form', [
            'user' => $model->toAdminArray(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $model = User::query()->where('public_id', $user)->firstOrFail();
        $validated = $this->validateUser($request, $model->id);
        $role = Role::query()->findOrFail($validated['roleId']);

        $payload = [
            'name' => $validated['name'],
            'role_id' => $role->id,
            'email' => $validated['email'],
            'country_code' => $validated['countryCode'],
            'mobile_number' => $validated['mobileNumber'],
            'phone_key' => $validated['phoneKey'],
            'status' => $validated['status'],
        ];

        if ($model->role?->isSuperAdmin() && ($role->id !== $model->role_id || $validated['status'] !== 'active')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roleId' => 'The Super Admin role and active status cannot be changed.',
            ]);
        }

        if ($validated['password'] !== null) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $model->update($payload);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(string $user): RedirectResponse
    {
        $model = User::query()->with('role')->where('public_id', $user)->firstOrFail();

        if ($model->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($model->role?->isSuperAdmin()) {
            return back()->withErrors(['user' => 'The Super Admin account cannot be deleted.']);
        }

        $model->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    /** @return list<array{id: int, name: string, slug: string}> */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     name: string,
     *     roleId: int,
     *     email: string|null,
     *     password: string|null,
     *     countryCode: string|null,
     *     mobileNumber: string|null,
     *     phoneKey: string|null
     * }
     */
    private function validateUser(Request $request, ?int $ignoreUserId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'roleId' => ['required', 'integer', Rule::exists('roles', 'id')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'countryCode' => ['nullable', 'string', 'max:8'],
            'mobileNumber' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $role = Role::query()->findOrFail($validated['roleId']);
        $isMarketplace = $role->slug === 'marketplace_user';

        if ($isMarketplace) {
            $request->validate([
                'countryCode' => ['required', 'string', 'max:8'],
                'mobileNumber' => ['required', 'string', 'max:20'],
            ]);
        } else {
            $request->validate([
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
                'password' => [$ignoreUserId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            ]);
        }

        $phoneKey = null;
        $mobileNumber = isset($validated['mobileNumber']) ? preg_replace('/\D+/', '', $validated['mobileNumber']) : null;
        $countryCode = $validated['countryCode'] ?? null;

        if ($countryCode && $mobileNumber) {
            $phoneKey = PhoneNormalizer::key($countryCode, $mobileNumber);
            if ($phoneKey === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mobileNumber' => 'Enter a valid mobile number.',
                ]);
            }

            $exists = User::query()
                ->where('phone_key', $phoneKey)
                ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mobileNumber' => 'This mobile number is already registered.',
                ]);
            }
        }

        return [
            'name' => trim($validated['name']),
            'roleId' => (int) $validated['roleId'],
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'] ?? null,
            'countryCode' => $countryCode,
            'mobileNumber' => $mobileNumber,
            'phoneKey' => $phoneKey,
            'status' => $validated['status'],
        ];
    }
}
