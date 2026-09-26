<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminRecipeKitController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminSliderController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // Guest Admin Routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store']);
    });

    // Authenticated Admin Routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Product Management (with Multi-Image Uploads)
        Route::middleware('permission:manage products,admin')->group(function () {
            Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
            Route::post('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
            Route::put('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

            // Recipe Kits & Group Buy Product Bundles
            Route::get('/recipe-kits', [AdminRecipeKitController::class, 'index'])->name('recipe-kits.index');
            Route::get('/recipe-kits/create', [AdminRecipeKitController::class, 'create'])->name('recipe-kits.create');
            Route::post('/recipe-kits', [AdminRecipeKitController::class, 'store'])->name('recipe-kits.store');
            Route::get('/recipe-kits/{recipe_kit}/edit', [AdminRecipeKitController::class, 'edit'])->name('recipe-kits.edit');
            Route::post('/recipe-kits/{recipe_kit}', [AdminRecipeKitController::class, 'update'])->name('recipe-kits.update');
            Route::put('/recipe-kits/{recipe_kit}', [AdminRecipeKitController::class, 'update']);
            Route::delete('/recipe-kits/{recipe_kit}', [AdminRecipeKitController::class, 'destroy'])->name('recipe-kits.destroy');

            // Homepage Hero Carousel Sliders
            Route::get('/sliders', [AdminSliderController::class, 'index'])->name('sliders.index');
            Route::get('/sliders/create', [AdminSliderController::class, 'create'])->name('sliders.create');
            Route::post('/sliders', [AdminSliderController::class, 'store'])->name('sliders.store');
            Route::get('/sliders/{slider}/edit', [AdminSliderController::class, 'edit'])->name('sliders.edit');
            Route::post('/sliders/{slider}', [AdminSliderController::class, 'update'])->name('sliders.update');
            Route::put('/sliders/{slider}', [AdminSliderController::class, 'update']);
            Route::delete('/sliders/{slider}', [AdminSliderController::class, 'destroy'])->name('sliders.destroy');
        });

        // Role & Permission Management
        Route::middleware('permission:manage roles,admin')->group(function () {
            Route::post('/roles', [AdminRoleController::class, 'storeRole'])->name('roles.store');
            Route::post('/admins/{admin}/roles', [AdminRoleController::class, 'syncAdminRoles'])->name('admins.roles.sync');
        });
    });
});
