<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Dashboard\BankAccountController;
use App\Http\Controllers\Dashboard\CompanyProfileController;
use App\Http\Controllers\Dashboard\EquipmentItemController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\UserDutyStatusController;
use App\Http\Controllers\Dashboard\VehicleController;
use App\Http\Controllers\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Dashboard\VehicleRentalLimitController;
use App\Http\Controllers\Dashboard\VehicleScheduleController;
use App\Http\Controllers\DashboardChartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VehicleCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/vehicles', [VehicleCatalogController::class, 'index'])->name('vehicles.index');
Route::get('/vehicles/{vehicle}', [VehicleCatalogController::class, 'show'])->name('vehicles.show');

Route::middleware(['auth', 'active', 'role:user'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/items/{vehicle}', [CheckoutController::class, 'addItem'])->name('checkout.items.store');
    Route::delete('/checkout/items/{index}', [CheckoutController::class, 'removeItem'])->whereNumber('index')->name('checkout.items.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('dashboard')->middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('charts')->name('dashboard.charts.')->group(function () {
        Route::get('top-vehicles', [DashboardChartController::class, 'topVehicles'])->name('top-vehicles');
        Route::get('order-status', [DashboardChartController::class, 'orderStatus'])->name('order-status');
        Route::get('revenue', [DashboardChartController::class, 'revenue'])->name('revenue');
    });

    Route::resource('vehicles', VehicleController::class)->names('dashboard.vehicles');
    Route::resource('vehicles.schedules', VehicleScheduleController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped()
        ->names('dashboard.vehicles.schedules');
    Route::post('vehicles/{vehicle}/odometer', [VehicleOdometerController::class, 'store'])->name('dashboard.vehicles.odometer.store');
    Route::put('vehicles/{vehicle}/rental-limit', [VehicleRentalLimitController::class, 'update'])->name('dashboard.vehicles.rental-limit.update');

    Route::resource('equipment', EquipmentItemController::class)
        ->except(['show', 'destroy'])
        ->names('dashboard.equipment');

    Route::get('users', [UserController::class, 'index'])->name('dashboard.users.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('company', [CompanyProfileController::class, 'edit'])->name('dashboard.company.edit');
        Route::put('company', [CompanyProfileController::class, 'update'])->name('dashboard.company.update');
        Route::resource('company/bank-accounts', BankAccountController::class)
            ->only(['store', 'update'])
            ->parameters(['bank-accounts' => 'bank_account'])
            ->names('dashboard.company.bank-accounts');
        Route::resource('users', UserController::class)->except(['index', 'show'])->names('dashboard.users');
        Route::patch('users/{user}/duty-status', UserDutyStatusController::class)->name('dashboard.users.duty-status');
    });
});
