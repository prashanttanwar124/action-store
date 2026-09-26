<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeKitController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1a: Home / Storefront
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [HomeController::class, 'search'])->name('search');

// Recipe Kit Detail Page (Group Buy Bundle)
Route::get('/recipe-kits/{slug}', [RecipeKitController::class, 'show'])->name('recipe-kits.show');

// 1b & 1c: Product detail pages (e.g. /products/paneer, /products/rice, /products/atta)
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

// 1d: Smart Cart & Checkout
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');

// 1f: Customer Dashboard & Account
Route::get('/account', [AccountController::class, 'index'])->name('account');
Route::get('/reorder', [AccountController::class, 'reorder'])->name('reorder');

// Breeze default dashboard & profile routes
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
