<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Dashboard\EquipmentItemController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleAvailabilityController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleScheduleController;
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

    Route::prefix('dashboard')->name('dashboard.')->middleware(['auth:sanctum', 'active', 'role:admin,operator'])->group(function () {
        Route::apiResource('vehicles', VehicleController::class);
        Route::apiResource('equipment', EquipmentItemController::class)->except('destroy');
        Route::get('vehicles/{vehicle}/free-windows', [VehicleAvailabilityController::class, 'freeWindows'])->name('vehicles.free-windows');
        Route::get('vehicles/{vehicle}/timeline', [VehicleAvailabilityController::class, 'timeline'])->name('vehicles.timeline');
        Route::apiResource('vehicles.schedules', VehicleScheduleController::class)->except('show')->scoped();
        Route::post('vehicles/{vehicle}/odometer', [VehicleOdometerController::class, 'store'])->name('vehicles.odometer.store');
    });
});
