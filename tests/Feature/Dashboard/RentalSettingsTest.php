<?php

use App\Actions\Orders\CreateOrder;
use App\Models\Order;
use App\Models\RentalSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

function rentalSettingsPayload(array $overrides = []): array
{
    return $overrides + RentalSetting::factory()->raw();
}

it('rejects guests, customers, and operators on the rental settings endpoints', function () {
    $this->get(route('dashboard.settings.rental.edit'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('dashboard.settings.rental.edit'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->get(route('dashboard.settings.rental.edit'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->put(route('dashboard.settings.rental.update'), rentalSettingsPayload())->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->getJson(route('api.v1.dashboard.settings.rental.show'))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->putJson(route('api.v1.dashboard.settings.rental.update'), rentalSettingsPayload())->assertForbidden();
});

it('shows the settings with defaults and the menu only for admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.settings.rental.edit'))
        ->assertOk()
        ->assertSee('Pengaturan Sewa')
        ->assertSee('name="max_pending_orders"', false)
        ->assertSee('value="2"', false)
        ->assertDontSee('checkout_lock_wait_seconds')
        ->assertSee(route('dashboard.settings.rental.edit'), false);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertDontSee(route('dashboard.settings.rental.edit'), false);
});

it('defaults the pending order limit to two', function () {
    expect(config('rental.max_pending_orders'))->toBe(2)
        ->and(app(RentalSettings::class)->defaults()['max_pending_orders'])->toBe(2);
});

it('saves the settings and applies them to the config immediately', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.settings.rental.update'), rentalSettingsPayload(['return_buffer_hours' => 5, 'max_pending_orders' => 4, 'vehicle_change_admin_threshold' => 250000]))
        ->assertRedirect(route('dashboard.settings.rental.edit'))
        ->assertSessionHasNoErrors();

    expect(RentalSetting::sole()->return_buffer_hours)->toBe(5)
        ->and(config('rental.return_buffer_hours'))->toBe(5)
        ->and(config('rental.max_pending_orders'))->toBe(4)
        ->and(config('rental.vehicle_change_admin_threshold'))->toBe(250000);
});

it('loads saved values into the config on boot and falls back to the defaults for empty columns', function () {
    RentalSetting::factory()->create(['return_buffer_hours' => 6, 'max_rental_days' => null]);
    Cache::forget(RentalSettings::CACHE_KEY);

    app(RentalSettings::class)->apply();

    expect(config('rental.return_buffer_hours'))->toBe(6)
        ->and(config('rental.max_rental_days'))->toBe(7);
});

it('uses the saved pending limit when creating an order', function () {
    $customer = User::factory()->create();
    Order::factory()->count(2)->for($customer)->create();
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.settings.rental.update'), rentalSettingsPayload(['max_pending_orders' => 3]));

    expect(app(CreateOrder::class)->handle($customer, now()->addDay()->toDateTimeString(), 1, [$vehicle->id]))->toBeInstanceOf(Order::class);

    $this->put(route('dashboard.settings.rental.update'), rentalSettingsPayload(['max_pending_orders' => 2]));

    expect(fn () => app(CreateOrder::class)->handle($customer, now()->addDays(5)->toDateTimeString(), 1, [Vehicle::factory()->create()->id]))
        ->toThrow(ValidationException::class);
});

it('rejects values outside their limits and inconsistent combinations', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.settings.rental.update'), rentalSettingsPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(RentalSetting::query()->whereNotNull('max_pending_orders')->count())->toBe(0);
})->with([
    'buffer above 24 hours' => [['return_buffer_hours' => 25], 'return_buffer_hours'],
    'negative buffer' => [['return_buffer_hours' => -1], 'return_buffer_hours'],
    'max rental days above 30' => [['max_rental_days' => 31], 'max_rental_days'],
    'pending limit zero' => [['max_pending_orders' => 0], 'max_pending_orders'],
    'not a number' => [['pending_expiry_hours' => 'abc'], 'pending_expiry_hours'],
    'missing value' => [['max_vehicles_per_order' => null], 'max_vehicles_per_order'],
    'minimum longer than maximum' => [['min_rental_hours' => 72, 'max_rental_days' => 2], 'min_rental_hours'],
    'warning longer than verification window' => [['verification_warning_hours' => 30, 'confirm_validation_window_hours' => 24], 'verification_warning_hours'],
]);

it('reads and updates the settings through the api', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.dashboard.settings.rental.show'))
        ->assertOk()
        ->assertJsonPath('data.values.max_pending_orders', 2)
        ->assertJsonPath('data.defaults.return_buffer_hours', 3)
        ->assertJsonPath('data.limits.max_rental_days.max', 30);

    $this->putJson(route('api.v1.dashboard.settings.rental.update'), rentalSettingsPayload(['max_pending_orders' => 5]))
        ->assertOk()
        ->assertJsonPath('data.values.max_pending_orders', 5);

    $this->putJson(route('api.v1.dashboard.settings.rental.update'), rentalSettingsPayload(['max_pending_orders' => 99]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_pending_orders');
});
