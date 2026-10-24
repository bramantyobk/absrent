<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutItemController;
use App\Http\Controllers\CheckoutRescheduleController;
use App\Http\Controllers\CheckoutTermsController;
use App\Http\Controllers\Dashboard\BankAccountController;
use App\Http\Controllers\Dashboard\CancellationRequestController;
use App\Http\Controllers\Dashboard\CompanyProfileController;
use App\Http\Controllers\Dashboard\CustomerLookupController;
use App\Http\Controllers\Dashboard\EquipmentItemController;
use App\Http\Controllers\Dashboard\InspectionController;
use App\Http\Controllers\Dashboard\ManualOrderController;
use App\Http\Controllers\Dashboard\OldVehicleController;
use App\Http\Controllers\Dashboard\OrderCancelController;
use App\Http\Controllers\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Dashboard\OrderStatusController;
use App\Http\Controllers\Dashboard\OrderVehicleChangeController;
use App\Http\Controllers\Dashboard\RentalSettingController;
use App\Http\Controllers\Dashboard\RevenueController;
use App\Http\Controllers\Dashboard\TermsController;
use App\Http\Controllers\Dashboard\TransactionController;
use App\Http\Controllers\Dashboard\TransactionProofController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\UserDutyStatusController;
use App\Http\Controllers\Dashboard\UserPasswordResetLinkController;
use App\Http\Controllers\Dashboard\UserPhoneReviewController;
use App\Http\Controllers\Dashboard\VehicleController;
use App\Http\Controllers\Dashboard\VehicleOdometerController;
use App\Http\Controllers\Dashboard\VehicleRentalLimitController;
use App\Http\Controllers\Dashboard\VehicleScheduleController;
use App\Http\Controllers\Dashboard\WhatsAppTemplateController;
use App\Http\Controllers\DashboardChartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\OrderCancellationRequestController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\OrderProofController;
use App\Http\Controllers\OrderWhatsAppController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\VehicleCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/vehicles', [VehicleCatalogController::class, 'index'])->name('vehicles.index');
Route::get('/vehicles/{vehicle:slug}', [VehicleCatalogController::class, 'show'])->name('vehicles.show');

Route::middleware(['auth', 'active', 'role:user'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/checkout/terms', [CheckoutTermsController::class, 'show'])->name('checkout.terms');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/items/{vehicle}', [CheckoutItemController::class, 'store'])->name('checkout.items.store');
    Route::post('/checkout/reschedule', [CheckoutRescheduleController::class, 'store'])->name('checkout.reschedule');
    Route::delete('/checkout/items/{index}', [CheckoutItemController::class, 'destroy'])->whereNumber('index')->name('checkout.items.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::put('/profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/payment', [OrderPaymentController::class, 'show'])->name('orders.payment');
    Route::post('/orders/{order}/confirm', [OrderPaymentController::class, 'store'])->name('orders.confirm');
    Route::get('/orders/{order}/whatsapp', OrderWhatsAppController::class)->name('orders.whatsapp');
    Route::post('/orders/{order}/cancel', [OrderCancellationController::class, 'store'])->name('orders.cancel');
    Route::post('/orders/{order}/cancellation-request', [OrderCancellationRequestController::class, 'store'])->name('orders.cancellation-request');
    Route::get('/orders/{order}/proof', OrderProofController::class)->name('orders.proof');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('dashboard')->middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('charts')->name('dashboard.charts.')->group(function () {
        Route::get('top-vehicles', [DashboardChartController::class, 'topVehicles'])->name('top-vehicles');
        Route::get('order-status', [DashboardChartController::class, 'orderStatus'])->name('order-status');
        Route::get('revenue', [DashboardChartController::class, 'revenue'])->name('revenue');
    });

    Route::get('orders', [DashboardOrderController::class, 'index'])->name('dashboard.orders.index');
    Route::get('orders/manual', [ManualOrderController::class, 'create'])->name('dashboard.orders.manual.create');
    Route::post('orders/manual', [ManualOrderController::class, 'store'])->name('dashboard.orders.manual.store');
    Route::get('customers/lookup', CustomerLookupController::class)->name('dashboard.customers.lookup');
    Route::get('orders/{order}', [DashboardOrderController::class, 'show'])->name('dashboard.orders.show');
    Route::post('orders/{order}/cancel', [OrderCancelController::class, 'store'])->name('dashboard.orders.cancel');
    Route::patch('orders/{order}/status', [OrderStatusController::class, 'update'])->name('dashboard.orders.status.update');
    Route::post('orders/{order}/items/{item}/vehicle-change', [OrderVehicleChangeController::class, 'store'])->scopeBindings()->name('dashboard.orders.items.vehicle-change.store');
    Route::patch('vehicle-changes/{change}/old-vehicle', [OldVehicleController::class, 'update'])->name('dashboard.vehicle-changes.old-vehicle.update');

    Route::get('revenue', [RevenueController::class, 'index'])->name('dashboard.revenue.index');

    Route::get('transactions', [TransactionController::class, 'index'])->name('dashboard.transactions.index');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('dashboard.transactions.show');
    Route::post('transactions/{transaction}/verify', [TransactionController::class, 'verify'])->name('dashboard.transactions.verify');
    Route::get('transactions/{transaction}/proof', TransactionProofController::class)->name('dashboard.transactions.proof');

    Route::get('cancellation-requests', [CancellationRequestController::class, 'index'])->name('dashboard.cancellation-requests.index');
    Route::patch('cancellation-requests/{cancellationRequest}', [CancellationRequestController::class, 'update'])->name('dashboard.cancellation-requests.update');

    Route::resource('vehicles', VehicleController::class)->names('dashboard.vehicles');
    Route::resource('vehicles.schedules', VehicleScheduleController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped()
        ->names('dashboard.vehicles.schedules');
    Route::post('vehicles/{vehicle}/odometer', [VehicleOdometerController::class, 'store'])->name('dashboard.vehicles.odometer.store');
    Route::put('vehicles/{vehicle}/rental-limit', [VehicleRentalLimitController::class, 'update'])->name('dashboard.vehicles.rental-limit.update');

    Route::resource('equipment', EquipmentItemController::class)
        ->except(['show'])
        ->names('dashboard.equipment');

    Route::get('users', [UserController::class, 'index'])->name('dashboard.users.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('company', [CompanyProfileController::class, 'edit'])->name('dashboard.company.edit');
        Route::put('company', [CompanyProfileController::class, 'update'])->name('dashboard.company.update');
        Route::resource('company/bank-accounts', BankAccountController::class)
            ->only(['store', 'update'])
            ->parameters(['bank-accounts' => 'bank_account'])
            ->names('dashboard.company.bank-accounts');
        Route::patch('inspections/{inspection}', [InspectionController::class, 'update'])->name('dashboard.inspections.update');
        Route::put('company/whatsapp-template', [WhatsAppTemplateController::class, 'update'])->name('dashboard.company.whatsapp-template.update');
        Route::get('terms', [TermsController::class, 'edit'])->name('dashboard.terms.edit');
        Route::put('terms', [TermsController::class, 'update'])->name('dashboard.terms.update');
        Route::get('settings/rental', [RentalSettingController::class, 'edit'])->name('dashboard.settings.rental.edit');
        Route::put('settings/rental', [RentalSettingController::class, 'update'])->name('dashboard.settings.rental.update');
        Route::resource('users', UserController::class)->except(['index', 'show'])->names('dashboard.users');
        Route::patch('users/{user}/duty-status', UserDutyStatusController::class)->name('dashboard.users.duty-status');
        Route::patch('users/{user}/phone-review', UserPhoneReviewController::class)->name('dashboard.users.phone-review');
        Route::post('users/{user}/password-reset-link', [UserPasswordResetLinkController::class, 'store'])->name('dashboard.users.password-reset-link');
    });
});
