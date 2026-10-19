<?php

use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignAdminController;
use App\Http\Controllers\Admin\DesignFileTypeAdminController;
use App\Http\Controllers\Admin\MachineAdminController;
use App\Http\Controllers\Admin\MachineTypeAdminController;
use App\Http\Controllers\Admin\PurchaseAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TypeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('admin/settings/site', [SiteSettingController::class, 'edit'])
        ->middleware('permission:site_setting_access,read')
        ->name('admin.settings.site.edit');
    Route::post('admin/settings/site', [SiteSettingController::class, 'update'])
        ->middleware('permission:site_setting_access,update')
        ->name('admin.settings.site.update');

    Route::get('admin/roles', [RoleAdminController::class, 'index'])
        ->middleware('permission:role_access,read')
        ->name('admin.roles.index');
    Route::post('admin/roles', [RoleAdminController::class, 'store'])
        ->middleware('permission:role_access,create')
        ->name('admin.roles.store');
    Route::put('admin/roles/{role}/permissions', [RoleAdminController::class, 'updatePermissions'])
        ->middleware('permission:role_access,update')
        ->name('admin.roles.permissions.update');
    Route::delete('admin/roles/{role}', [RoleAdminController::class, 'destroy'])
        ->middleware('permission:role_access,delete')
        ->name('admin.roles.destroy');

    Route::resource('admin/categories', CategoryAdminController::class)
        ->parameters(['categories' => 'category'])
        ->names('admin.categories')
        ->except(['show']);

    Route::resource('admin/machines', MachineAdminController::class)
        ->parameters(['machines' => 'machine'])
        ->names('admin.machines')
        ->except(['show']);

    Route::resource('admin/machine-types', MachineTypeAdminController::class)
        ->parameters(['machine-types' => 'machineType'])
        ->names('admin.machine-types')
        ->except(['show']);

    Route::resource('admin/types', TypeAdminController::class)
        ->parameters(['types' => 'type'])
        ->names('admin.types')
        ->except(['show']);

    Route::resource('admin/design-file-types', DesignFileTypeAdminController::class)
        ->parameters(['design-file-types' => 'designFileType'])
        ->names('admin.design-file-types')
        ->except(['show']);

    Route::resource('admin/designs', DesignAdminController::class)
        ->parameters(['designs' => 'design'])
        ->names('admin.designs')
        ->except(['show']);

    Route::resource('admin/users', UserAdminController::class)
        ->parameters(['users' => 'user'])
        ->names('admin.users')
        ->except(['show']);

    Route::resource('admin/purchases', PurchaseAdminController::class)
        ->parameters(['purchases' => 'purchase'])
        ->names('admin.purchases')
        ->only(['index', 'edit', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
