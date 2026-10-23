<?php

use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignAdminController;
use App\Http\Controllers\Admin\DesignFileTypeAdminController;
use App\Http\Controllers\Admin\FolderAdminController;
use App\Http\Controllers\Admin\MachineAdminController;
use App\Http\Controllers\Admin\MachineTypeAdminController;
use App\Http\Controllers\Admin\PurchaseAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TypeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.read')
        ->name('dashboard');

    Route::get('admin/settings/site', [SiteSettingController::class, 'edit'])
        ->middleware('permission:branding.read')
        ->name('admin.settings.site.edit');
    Route::post('admin/settings/site', [SiteSettingController::class, 'update'])
        ->middleware('permission:branding.update')
        ->name('admin.settings.site.update');

    Route::get('admin/roles', [RoleAdminController::class, 'index'])
        ->middleware('permission:roles.read')
        ->name('admin.roles.index');
    Route::post('admin/roles', [RoleAdminController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('admin.roles.store');
    Route::put('admin/roles/{role}/permissions', [RoleAdminController::class, 'updatePermissions'])
        ->middleware('permission:roles.assign_permissions')
        ->name('admin.roles.permissions.update');
    Route::delete('admin/roles/{role}', [RoleAdminController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('admin.roles.destroy');

    Route::resource('admin/categories', CategoryAdminController::class)
        ->parameters(['categories' => 'category'])
        ->names('admin.categories')
        ->except(['show'])
        ->middlewareFor('index', 'permission:categories.read')
        ->middlewareFor(['create', 'store'], 'permission:categories.create')
        ->middlewareFor(['edit', 'update'], 'permission:categories.update')
        ->middlewareFor('destroy', 'permission:categories.delete');

    Route::resource('admin/machines', MachineAdminController::class)
        ->parameters(['machines' => 'machine'])
        ->names('admin.machines')
        ->except(['show'])
        ->middlewareFor('index', 'permission:machines.read')
        ->middlewareFor(['create', 'store'], 'permission:machines.create')
        ->middlewareFor(['edit', 'update'], 'permission:machines.update')
        ->middlewareFor('destroy', 'permission:machines.delete');

    Route::resource('admin/machine-types', MachineTypeAdminController::class)
        ->parameters(['machine-types' => 'machineType'])
        ->names('admin.machine-types')
        ->except(['show'])
        ->middlewareFor('index', 'permission:machine_types.read')
        ->middlewareFor(['create', 'store'], 'permission:machine_types.create')
        ->middlewareFor(['edit', 'update'], 'permission:machine_types.update')
        ->middlewareFor('destroy', 'permission:machine_types.delete');

    Route::resource('admin/types', TypeAdminController::class)
        ->parameters(['types' => 'type'])
        ->names('admin.types')
        ->except(['show'])
        ->middlewareFor('index', 'permission:design_types.read')
        ->middlewareFor(['create', 'store'], 'permission:design_types.create')
        ->middlewareFor(['edit', 'update'], 'permission:design_types.update')
        ->middlewareFor('destroy', 'permission:design_types.delete');

    Route::resource('admin/design-file-types', DesignFileTypeAdminController::class)
        ->parameters(['design-file-types' => 'designFileType'])
        ->names('admin.design-file-types')
        ->except(['show'])
        ->middlewareFor('index', 'permission:design_file_types.read')
        ->middlewareFor(['create', 'store'], 'permission:design_file_types.create')
        ->middlewareFor(['edit', 'update'], 'permission:design_file_types.update')
        ->middlewareFor('destroy', 'permission:design_file_types.delete');

    Route::resource('admin/designs', DesignAdminController::class)
        ->parameters(['designs' => 'design'])
        ->names('admin.designs')
        ->except(['show'])
        ->middlewareFor('index', 'permission:designs.read')
        ->middlewareFor(['create', 'store'], 'permission:designs.create')
        ->middlewareFor(['edit', 'update'], 'permission:designs.update')
        ->middlewareFor('destroy', 'permission:designs.delete');

    Route::resource('admin/folders', FolderAdminController::class)
        ->parameters(['folders' => 'folder'])
        ->names('admin.folders')
        ->except(['show'])
        ->middlewareFor('index', 'permission:folders.read')
        ->middlewareFor(['create', 'store'], 'permission:folders.create')
        ->middlewareFor(['edit', 'update'], 'permission:folders.update')
        ->middlewareFor('destroy', 'permission:folders.delete');

    Route::resource('admin/users', UserAdminController::class)
        ->parameters(['users' => 'user'])
        ->names('admin.users')
        ->except(['show'])
        ->middlewareFor('index', 'permission:users.read')
        ->middlewareFor(['create', 'store'], 'permission:users.create')
        ->middlewareFor(['edit', 'update'], 'permission:users.update')
        ->middlewareFor('destroy', 'permission:users.delete');

    Route::resource('admin/purchases', PurchaseAdminController::class)
        ->parameters(['purchases' => 'purchase'])
        ->names('admin.purchases')
        ->only(['index', 'edit', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:purchases.read')
        ->middlewareFor(['edit', 'update'], 'permission:purchases.update')
        ->middlewareFor('destroy', 'permission:purchases.delete');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
