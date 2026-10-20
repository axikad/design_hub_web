<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingController extends Controller
{
    public function edit(): Response
    {
        $settings = SiteSetting::current();

        return Inertia::render('admin/settings/site', [
            'settings' => [
                ...$settings->toBranding(),
                'defaultRegistrationRoleId' => $settings->default_registration_role_id,
            ],
            'registrationRoles' => Role::query()
                ->where('slug', '!=', 'super_admin')
                ->whereHas('permissions', fn ($query) => $query->where('slug', 'dashboard.read')->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,ico,svg', 'max:1024'],
            'defaultRegistrationRoleId' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', 'super_admin')),
            ],
        ]);

        $setting = SiteSetting::current();

        $registrationRole = Role::query()->findOrFail($validated['defaultRegistrationRoleId']);

        if (! $registrationRole->hasPermission('dashboard.read')) {
            return back()->withErrors([
                'defaultRegistrationRoleId' => 'The registration role must have dashboard access.',
            ]);
        }

        $payload = [
            'name' => trim($validated['name']),
            'default_registration_role_id' => $registrationRole->id,
        ];

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $payload['logo_path'] = $request->file('logo')->store('site', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }
            $payload['favicon_path'] = $request->file('favicon')->store('site', 'public');
        }

        $setting->update($payload);
        SiteSetting::forgetCache();

        return redirect()->route('admin.settings.site.edit')->with('success', 'Site settings saved.');
    }
}
