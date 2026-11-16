<?php

use App\Http\Controllers\Pos\PosAuthController;
use App\Http\Controllers\Pos\PosCatalogController;
use App\Http\Controllers\Pos\PosCustomerController;
use App\Http\Controllers\Pos\PosOrderController;
use App\Http\Controllers\Pos\PosReturnController;
use App\Http\Controllers\Pos\PosShiftController;
use Illuminate\Support\Facades\Route;

$registerPosRoutes = static function (): void {
    Route::get('/login', [PosAuthController::class, 'create'])->name('login');
    Route::post('/login', [PosAuthController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::middleware(['auth:admin', 'permission:operate pos,admin'])->group(function (): void {
        Route::post('/logout', [PosAuthController::class, 'destroy'])->name('logout');
        Route::post('/switch-cashier', [PosAuthController::class, 'switchCashier'])
            ->middleware('throttle:15,1')
            ->name('switch-cashier');

        Route::get('/', [PosOrderController::class, 'index'])->name('register');
        Route::get('/products', [PosCatalogController::class, 'index'])->name('products.index');
        Route::get('/products/scan', [PosCatalogController::class, 'scan'])->name('products.scan');
        Route::post('/orders', [PosOrderController::class, 'store'])->name('orders.store');

        Route::get('/shifts/current', [PosShiftController::class, 'current'])->name('shifts.current');
        Route::post('/shifts/open', [PosShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shifts/{shift}/movements', [PosShiftController::class, 'storeMovement'])->name('shifts.movements.store');
        Route::post('/shifts/{shift}/close', [PosShiftController::class, 'close'])->name('shifts.close');

        Route::get('/customers/search', [PosCustomerController::class, 'search'])->name('customers.search');
        Route::post('/customers', [PosCustomerController::class, 'store'])->name('customers.store');

        Route::get('/orders', [PosReturnController::class, 'index'])->name('orders.index');
        Route::post('/orders/{order}/refund', [PosReturnController::class, 'refund'])->name('orders.refund');
    });
};

$posDomain = trim((string) config('pos.domain', ''));

if ($posDomain !== '') {
    Route::domain($posDomain)
        ->name('pos.')
        ->middleware('pos.ip')
        ->group($registerPosRoutes);
} else {
    Route::prefix('pos')
        ->name('pos.')
        ->middleware('pos.ip')
        ->group($registerPosRoutes);
}
