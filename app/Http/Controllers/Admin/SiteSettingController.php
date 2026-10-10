<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/site', [
            'settings' => SiteSetting::current()->toBranding(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,ico,svg', 'max:1024'],
        ]);

        $setting = SiteSetting::current();

        $payload = ['name' => trim($validated['name'])];

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
