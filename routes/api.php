<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\CatalogVehicleController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\Dashboard\BankAccountController;
use App\Http\Controllers\Api\V1\Dashboard\CompanyProfileController;
use App\Http\Controllers\Api\V1\Dashboard\EquipmentItemController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleAvailabilityController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleRentalLimitController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleScheduleController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:api-auth')->group(function () {
            Route::post('register', [RegisterController::class, 'store'])->name('register');
            Route::post('login', [LoginController::class, 'store'])->name('login');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', [LogoutController::class, 'destroy'])->name('logout');
            Route::get('me', [MeController::class, 'show'])->name('me');
        });
    });

    Route::get('vehicles', [CatalogVehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/{vehicle}', [CatalogVehicleController::class, 'show'])->name('vehicles.show');
    Route::get('vehicles/{vehicle}/quote', [CatalogVehicleController::class, 'quote'])->name('vehicles.quote');
    Route::get('vehicles/{vehicle}/return-options', [CatalogVehicleController::class, 'returnOptions'])->name('vehicles.return-options');

    Route::middleware(['auth:sanctum', 'active', 'role:user'])->group(function () {
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    Route::prefix('dashboard')->name('dashboard.')->middleware(['auth:sanctum', 'active', 'role:admin,operator'])->group(function () {
        Route::apiResource('vehicles', VehicleController::class);
        Route::apiResource('equipment', EquipmentItemController::class)->except('destroy');
        Route::get('vehicles/{vehicle}/free-windows', [VehicleAvailabilityController::class, 'freeWindows'])->name('vehicles.free-windows');
        Route::get('vehicles/{vehicle}/timeline', [VehicleAvailabilityController::class, 'timeline'])->name('vehicles.timeline');
        Route::apiResource('vehicles.schedules', VehicleScheduleController::class)->except('show')->scoped();
        Route::post('vehicles/{vehicle}/odometer', [VehicleOdometerController::class, 'store'])->name('vehicles.odometer.store');
        Route::put('vehicles/{vehicle}/rental-limit', [VehicleRentalLimitController::class, 'update'])->name('vehicles.rental-limit.update');

        Route::middleware('role:admin')->group(function () {
            Route::get('company', [CompanyProfileController::class, 'show'])->name('company.show');
            Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');
            Route::apiResource('company/bank-accounts', BankAccountController::class)
                ->except('destroy')
                ->parameters(['bank-accounts' => 'bank_account'])
                ->names('company.bank-accounts');
        });
    });
});
