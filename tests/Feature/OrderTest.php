<?php

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;

function customerOrder(User $customer, array $overrides = []): Order
{
    $order = Order::factory()->for($customer)->create($overrides + ['total_price' => 150000]);
    OrderItem::factory()->for($order, 'order')->for(Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']), 'vehicle')->create();
    Transaction::factory()->for($order)->create();

    return $order;
}

it('lists only the customer own orders, newest first', function () {
    $customer = User::factory()->create();
    $older = customerOrder($customer, ['code' => 'ABS-OLDER001']);
    $newer = customerOrder($customer, ['code' => 'ABS-NEWER002']);
    customerOrder(User::factory()->create(), ['code' => 'ABS-OTHER003']);

    $this->actingAs($customer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSeeInOrder(['ABS-NEWER002', 'ABS-OLDER001'])
        ->assertDontSee('ABS-OTHER003');
});

it('shows an empty state without orders', function () {
    $this->actingAs(User::factory()->create())->get(route('orders.index'))->assertSee('Anda belum memiliki pesanan');
});

it('shows the order detail with items, total, status, and a support link', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $customer = User::factory()->create();
    $order = customerOrder($customer, ['code' => 'ABS-DETAIL01']);

    $this->actingAs($customer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('ABS-DETAIL01')
        ->assertSee('Honda Genio')
        ->assertSee('Rp150.000')
        ->assertSee('Pending')
        ->assertSee('https://wa.me/6281200000000?text=Halo%2C%20saya%20butuh%20bantuan%20untuk%20pesanan%20ABS-DETAIL01.', false);
});

it('returns 404 for another customer order so its id is not confirmed', function () {
    $order = customerOrder(User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertNotFound();
});

it('forbids staff on the customer order pages', function () {
    $order = customerOrder(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create())->get(route('orders.show', $order))->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get(route('orders.show', customerOrder(User::factory()->create())))->assertRedirect(route('login'));
});
