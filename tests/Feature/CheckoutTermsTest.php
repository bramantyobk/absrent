<?php

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function addToCheckout(Vehicle $vehicle, array $period = []): void
{
    test()->post(route('checkout.items.store', $vehicle), $period + ['start_at' => '2026-10-14T09:00', 'days' => 2]);
}

it('requires a customer account for the terms page', function () {
    $this->get(route('checkout.terms'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->get(route('checkout.terms'))->assertForbidden();
});

it('sends the checkout button to the terms page without creating an order', function () {
    $this->actingAs(User::factory()->create());
    addToCheckout(Vehicle::factory()->create());

    $this->get(route('checkout.show'))->assertSee(route('checkout.terms'), false);

    expect(Order::count())->toBe(0);
});

it('fills the terms with the customer, vehicles with plate numbers, period, and total', function () {
    CompanyProfile::factory()->create(['name' => 'ABSRENT Bali', 'terms_template' => "# Perjanjian\nPenyewa {name} ({phone}, {email}) menyewa:\n{vehicles}\nMulai {start_at} sampai {end_at}, {days} hari, total {total_price}. Pihak {company_name}."]);
    $customer = User::factory()->create(['name' => 'Budi Santoso', 'phone' => '6281234567890', 'email' => 'budi@example.com']);
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'DK 1234 AB', 'price_per_day' => 75000]);
    $this->actingAs($customer);
    addToCheckout($vehicle);

    $this->get(route('checkout.terms'))
        ->assertOk()
        ->assertSee('Perjanjian')
        ->assertSee('Penyewa Budi Santoso (6281234567890, budi@example.com) menyewa:')
        ->assertSee('1. Honda Genio (DK 1234 AB)')
        ->assertSee('Rabu, 14/10/2026 09:00 WIB')
        ->assertSee('Jumat, 16/10/2026 09:00 WIB')
        ->assertSee('2 hari, total Rp150.000. Pihak ABSRENT Bali.')
        ->assertSee('name="terms_accepted"', false)
        ->assertDontSee('<textarea', false);

    expect(Order::count())->toBe(0);
});

it('escapes html in the template', function () {
    CompanyProfile::factory()->create(['terms_template' => '<script>alert(1)</script> {name}']);
    $this->actingAs(User::factory()->create(['name' => 'Budi']));
    addToCheckout(Vehicle::factory()->create());

    $this->get(route('checkout.terms'))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

it('redirects to the checkout when it is empty, has an invalid item, or the pending limit is reached', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);

    $this->get(route('checkout.terms'))->assertRedirect(route('checkout.show'))->assertSessionHas('error');

    $vehicle = Vehicle::factory()->create();
    addToCheckout($vehicle);
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');

    $this->get(route('checkout.terms'))->assertRedirect(route('checkout.show'))->assertSessionHas('error');

    $this->post(route('checkout.reschedule'));
    $this->post(route('checkout.items.destroy', 0));
    Order::factory()->count(config('rental.max_pending_orders'))->for($customer)->create();
    addToCheckout(Vehicle::factory()->create());

    $this->get(route('checkout.terms'))->assertRedirect(route('checkout.show'))->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'pesanan yang belum dikonfirmasi'));
});

it('does not create the order until the terms are accepted', function () {
    $this->actingAs(User::factory()->create());
    addToCheckout(Vehicle::factory()->create());

    $this->from(route('checkout.terms'))->post(route('checkout.store'))->assertRedirect(route('checkout.terms'))->assertSessionHasErrors('terms_accepted');
    $this->from(route('checkout.terms'))->post(route('checkout.store'), ['terms_accepted' => 0])->assertSessionHasErrors('terms_accepted');

    expect(Order::count())->toBe(0);

    $this->post(route('checkout.store'), ['terms_accepted' => 1])->assertRedirect(route('orders.payment', Order::sole()));
});
