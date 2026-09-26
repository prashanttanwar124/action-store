<?php

use App\Http\Controllers\ProfileController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1a: Home / Storefront
Route::get('/', function () {
    return Inertia::render('Home', [
        'products' => Product::all(),
    ]);
})->name('home');

// 1b & 1c: Product detail pages (e.g. /products/paneer, /products/rice, /products/atta)
Route::get('/products/{slug}', function (string $slug) {
    $product = Product::where('slug', $slug)->first();

    return Inertia::render('ProductDetail', [
        'slug' => $slug,
        'product' => $product,
    ]);
})->name('products.show');

// 1d: Smart Cart
Route::get('/cart', function () {
    return Inertia::render('Cart');
})->name('cart');

// 1e: Checkout fulfilment & payment
Route::get('/checkout', function () {
    return Inertia::render('Checkout');
})->name('checkout');

// 1f: Customer Dashboard & Account
Route::get('/account', function () {
    return Inertia::render('Account');
})->name('account');

// Additional navigation routes for bottom bar
Route::get('/reorder', function () {
    return Inertia::render('Account', [
        'activeSection' => 'reorder',
    ]);
})->name('reorder');

Route::get('/search', function () {
    return Inertia::render('Home', [
        'focusSearch' => true,
    ]);
})->name('search');

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
