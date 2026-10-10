<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\Admin\DashboardController::class)->name('dashboard');

    Route::get('admin/settings/site', [\App\Http\Controllers\Admin\SiteSettingController::class, 'edit'])
        ->middleware('permission:site_setting_access,read')
        ->name('admin.settings.site.edit');
    Route::post('admin/settings/site', [\App\Http\Controllers\Admin\SiteSettingController::class, 'update'])
        ->middleware('permission:site_setting_access,update')
        ->name('admin.settings.site.update');

    Route::get('admin/roles', [\App\Http\Controllers\Admin\RoleAdminController::class, 'index'])
        ->middleware('permission:role_access,read')
        ->name('admin.roles.index');
    Route::post('admin/roles', [\App\Http\Controllers\Admin\RoleAdminController::class, 'store'])
        ->middleware('permission:role_access,create')
        ->name('admin.roles.store');
    Route::put('admin/roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleAdminController::class, 'updatePermissions'])
        ->middleware('permission:role_access,update')
        ->name('admin.roles.permissions.update');
    Route::delete('admin/roles/{role}', [\App\Http\Controllers\Admin\RoleAdminController::class, 'destroy'])
        ->middleware('permission:role_access,delete')
        ->name('admin.roles.destroy');

    Route::resource('admin/categories', \App\Http\Controllers\Admin\CategoryAdminController::class)
        ->parameters(['categories' => 'category'])
        ->names('admin.categories')
        ->except(['show']);

    Route::resource('admin/types', \App\Http\Controllers\Admin\TypeAdminController::class)
        ->parameters(['types' => 'type'])
        ->names('admin.types')
        ->except(['show']);

    Route::resource('admin/designs', \App\Http\Controllers\Admin\DesignAdminController::class)
        ->parameters(['designs' => 'design'])
        ->names('admin.designs')
        ->except(['show']);

    Route::resource('admin/users', \App\Http\Controllers\Admin\UserAdminController::class)
        ->parameters(['users' => 'user'])
        ->names('admin.users')
        ->except(['show']);

    Route::resource('admin/purchases', \App\Http\Controllers\Admin\PurchaseAdminController::class)
        ->parameters(['purchases' => 'purchase'])
        ->names('admin.purchases')
        ->only(['index', 'edit', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
