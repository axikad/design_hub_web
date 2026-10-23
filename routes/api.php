<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DesignController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/request-otp', [AuthController::class, 'requestOtp']);
    Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp']);

    Route::post('payments/webhook', [PaymentController::class, 'webhook']);

    Route::get('files/stitch/{design}', [DesignController::class, 'streamStitch'])
        ->name('files.stitch')
        ->middleware('signed');
    Route::get('files/folders/{folderId}/{fileId}/preview', [FolderController::class, 'streamPreview'])
        ->name('files.folders.preview')
        ->middleware('signed');
    Route::get('files/folders/{folderId}/download', [FolderController::class, 'streamDownload'])
        ->name('files.folders.download')
        ->middleware('signed');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('home', [HomeController::class, 'index'])->name('home');
        Route::get('catalog/categories', [CatalogController::class, 'categories']);
        Route::get('catalog/design-options', [CatalogController::class, 'designOptions']);
        Route::get('catalog/design-file-types', [CatalogController::class, 'designFileTypes']);
        Route::get('catalog/machines', [CatalogController::class, 'machines']);
        Route::get('catalog/machines/{machineId}/machine-types', [CatalogController::class, 'machineTypes']);
        Route::get('catalog/machine-types/{machineTypeId}/categories', [CatalogController::class, 'machineTypeCategories']);
        Route::get('catalog/categories/{categoryId}/types', [CatalogController::class, 'categoryTypes']);

        Route::get('users/me', [UserController::class, 'me']);
        Route::get('users/me/designs', [UserController::class, 'myDesigns']);
        Route::get('users/me/purchases', [UserController::class, 'purchases']);
        Route::get('users/me/saved-designs', [UserController::class, 'savedDesigns']);
        Route::put('users/me/saved-designs/{designId}', [UserController::class, 'saveDesign']);
        Route::delete('users/me/saved-designs/{designId}', [UserController::class, 'unsaveDesign']);

        Route::get('designs', [DesignController::class, 'index'])->name('designs.index');
        Route::post('designs', [DesignController::class, 'store']);
        Route::get('designs/{designId}', [DesignController::class, 'show']);
        Route::patch('designs/{designId}', [DesignController::class, 'update']);
        Route::delete('designs/{designId}', [DesignController::class, 'destroy']);
        Route::post('designs/{designId}/download', [DesignController::class, 'download']);

        Route::get('folders', [FolderController::class, 'index'])->name('folders.index');
        Route::get('folders/{folderId}', [FolderController::class, 'show'])->name('folders.show');
        Route::post('folders/{folderId}/download', [FolderController::class, 'download']);

        Route::post('uploads', [UploadController::class, 'store']);

        Route::get('purchases/designs/{designId}', [PaymentController::class, 'purchaseStatus']);
        Route::get('purchases/folders/{folderId}', [PaymentController::class, 'folderPurchaseStatus']);
        Route::post('payments/orders', [PaymentController::class, 'createOrder']);
        Route::post('payments/verify', [PaymentController::class, 'verify']);
    });
});
