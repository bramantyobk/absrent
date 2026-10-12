<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Dashboard\BankAccountController;
use App\Http\Controllers\Dashboard\CompanyProfileController;
use App\Http\Controllers\Dashboard\EquipmentItemController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\UserDutyStatusController;
use App\Http\Controllers\Dashboard\VehicleController;
use App\Http\Controllers\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Dashboard\VehicleScheduleController;
use App\Http\Controllers\DashboardChartController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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
