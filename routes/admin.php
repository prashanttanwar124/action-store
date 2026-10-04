<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPickupSettingController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminRecipeKitController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminSliderController;
use App\Http\Controllers\Admin\AdminStoreSettingController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::model('customer', User::class);

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

        // Real-Time Live Orders (KDS) powered by Laravel Reverb
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status.update');

        // Product Management (with Multi-Image Uploads)
        Route::middleware('permission:manage products,admin')->group(function () {
            Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
            Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
            Route::post('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
            Route::put('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

            // Category Management
            Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
            Route::post('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
            Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
            Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('/categories/{category}/toggle', [AdminCategoryController::class, 'toggleActive'])->name('categories.toggle');

            // Supplier & Vendor Management
            Route::get('/suppliers', [AdminSupplierController::class, 'index'])->name('suppliers.index');
            Route::post('/suppliers', [AdminSupplierController::class, 'store'])->name('suppliers.store');
            Route::post('/suppliers/{supplier}', [AdminSupplierController::class, 'update'])->name('suppliers.update');
            Route::patch('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
            Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
            Route::delete('/suppliers/{supplier}', [AdminSupplierController::class, 'destroy'])->name('suppliers.destroy');
            Route::match(['post', 'patch'], '/suppliers/{supplier}/toggle', [AdminSupplierController::class, 'toggleStatus'])->name('suppliers.toggle');
            Route::match(['post', 'patch'], '/suppliers/{supplier}/toggle-status', [AdminSupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');

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

            // Store Information & Branding
            Route::get('/store-info', [AdminStoreSettingController::class, 'edit'])->name('store-info.edit');
            Route::put('/store-info', [AdminStoreSettingController::class, 'update'])->name('store-info.update');
            Route::post('/store-info/busy-mode', [AdminStoreSettingController::class, 'quickBusyMode'])->name('store-info.busy-mode');

            // Dedicated Pickup & Slot Scheduling Settings
            Route::get('/pickup-settings', [AdminPickupSettingController::class, 'edit'])->name('pickup-settings.edit');
            Route::put('/pickup-settings', [AdminPickupSettingController::class, 'update'])->name('pickup-settings.update');
        });

        // Customer Accounts Management
        Route::middleware('permission:manage users,admin')->group(function () {
            Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
            Route::post('/customers', [AdminCustomerController::class, 'store'])->name('customers.store');
            Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
            Route::patch('/customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
            Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');
        });

        // Role & Permission Management
        Route::middleware('permission:manage roles,admin')->group(function () {
            Route::post('/roles', [AdminRoleController::class, 'storeRole'])->name('roles.store');
            Route::post('/admins/{admin}/roles', [AdminRoleController::class, 'syncAdminRoles'])->name('admins.roles.sync');
        });
    });
});
