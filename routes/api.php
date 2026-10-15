<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\CatalogVehicleController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\Dashboard\BankAccountController;
use App\Http\Controllers\Api\V1\Dashboard\CancellationRequestController;
use App\Http\Controllers\Api\V1\Dashboard\CompanyProfileController;
use App\Http\Controllers\Api\V1\Dashboard\EquipmentItemController;
use App\Http\Controllers\Api\V1\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Api\V1\Dashboard\TransactionController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleAvailabilityController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleRentalLimitController;
use App\Http\Controllers\Api\V1\Dashboard\VehicleScheduleController;
use App\Http\Controllers\Api\V1\Dashboard\WhatsAppTemplateController;
use App\Http\Controllers\Api\V1\OrderCancellationRequestController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\OrderProofController;
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
        Route::get('orders/{order}/payment', [OrderPaymentController::class, 'show'])->name('orders.payment');
        Route::post('orders/{order}/confirm', [OrderPaymentController::class, 'confirm'])->name('orders.confirm');
        Route::get('orders/{order}/whatsapp', [OrderPaymentController::class, 'whatsapp'])->name('orders.whatsapp');
        Route::post('orders/{order}/cancel', [OrderPaymentController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/cancellation-request', [OrderCancellationRequestController::class, 'store'])->name('orders.cancellation-request');
        Route::get('orders/{order}/proof', OrderProofController::class)->name('orders.proof');
    });

    Route::prefix('dashboard')->name('dashboard.')->middleware(['auth:sanctum', 'active', 'role:admin,operator'])->group(function () {
        Route::get('orders', [DashboardOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [DashboardOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [DashboardOrderController::class, 'status'])->name('orders.status');
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/verify', [TransactionController::class, 'verify'])->name('transactions.verify');
        Route::get('transactions/{transaction}/proof', [TransactionController::class, 'proof'])->name('transactions.proof');
        Route::get('cancellation-requests', [CancellationRequestController::class, 'index'])->name('cancellation-requests.index');
        Route::patch('cancellation-requests/{cancellationRequest}', [CancellationRequestController::class, 'update'])->name('cancellation-requests.update');

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
            Route::put('company/whatsapp-template', [WhatsAppTemplateController::class, 'update'])->name('company.whatsapp-template.update');
            Route::apiResource('company/bank-accounts', BankAccountController::class)
                ->except('destroy')
                ->parameters(['bank-accounts' => 'bank_account'])
                ->names('company.bank-accounts');
        });
    });
});
