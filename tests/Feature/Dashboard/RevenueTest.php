<?php

use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\Services\RevenueReport;
use App\TransactionStatus;
use App\VehicleType;

beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

it('rejects guests and customers on the revenue page', function () {
    $this->get(route('dashboard.revenue.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('dashboard.revenue.index'))->assertForbidden();
});

it('shows the revenue page to operators and admins, with a sidebar menu', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('dashboard.revenue.index'))
        ->assertOk()
        ->assertSee('Total pendapatan')
        ->assertSee(route('dashboard.revenue.index'), false)
        ->assertSee('Belum ada pendapatan pada periode ini');
})->with(['operator', 'admin']);

it('records rental revenue on the day the payment was verified', function () {
    revenueOrder('2026-10-18 09:00:00', 225000);
    revenueOrder('2026-10-18 20:00:00', 100000);
    revenueOrder('2026-10-19 08:00:00', 50000);
    $this->actingAs(User::factory()->operator()->create());

    $response = $this->get(route('dashboard.revenue.index', ['start_date' => '2026-10-15', 'end_date' => '2026-10-20']))->assertOk();

    $response->assertSee('Rp375.000')->assertSeeInOrder(['19/10/2026', 'Rp50.000', '18/10/2026', 'Rp325.000']);
});

it('counts booking, rented, completed, and stolen orders only', function (OrderStatus $status, TransactionStatus $transactionStatus, int $expected) {
    revenueOrder('2026-10-19 09:00:00', 100000, $status, null, $transactionStatus);
    $this->actingAs(User::factory()->operator()->create());

    $total = collect(app(RevenueReport::class)->daily(now()->subDays(5), now()))->sum('total');

    expect($total)->toBe($expected);
})->with([
    'booking' => [OrderStatus::Booking, TransactionStatus::Paid, 100000],
    'rented' => [OrderStatus::Rented, TransactionStatus::Paid, 100000],
    'completed' => [OrderStatus::Completed, TransactionStatus::Paid, 100000],
    'stolen keeps its payment' => [OrderStatus::Stolen, TransactionStatus::Paid, 100000],
    'refunded' => [OrderStatus::Refunded, TransactionStatus::Cancelled, 0],
    'cancelled' => [OrderStatus::Cancelled, TransactionStatus::Cancelled, 0],
    'failed' => [OrderStatus::Failed, TransactionStatus::Failed, 0],
    'awaiting verification' => [OrderStatus::Pending, TransactionStatus::Verifying, 0],
    'pending' => [OrderStatus::Pending, TransactionStatus::Pending, 0],
]);

it('records a vehicle change difference on the change date, and a reduction subtracts', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create();
    $order = revenueOrder('2026-10-18 09:00:00', 225000, OrderStatus::Booking, $oldVehicle);
    revenueChange($order, '2026-10-19 10:00:00', 75000);
    revenueChange($order, '2026-10-20 08:00:00', -25000);
    $this->actingAs(User::factory()->operator()->create());

    $days = app(RevenueReport::class)->daily(now()->subDays(3)->startOfDay(), now())->keyBy('day');

    expect($days['2026-10-18']['total'])->toBe(225000)
        ->and($days['2026-10-19'])->toMatchArray(['base' => 0, 'delta' => 75000, 'total' => 75000])
        ->and($days['2026-10-20']['total'])->toBe(-25000)
        ->and($days->sum('total'))->toBe(275000);

    $this->get(route('dashboard.revenue.index', ['start_date' => '2026-10-17', 'end_date' => '2026-10-20']))
        ->assertSee('Rp275.000')
        ->assertSee('-Rp25.000');
});

it('credits the whole order and its difference to the replacement unit', function () {
    $genio = Vehicle::factory()->motorcycle()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $nmax = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX']);
    $order = revenueOrder('2026-10-18 09:00:00', 225000, OrderStatus::Booking, $genio);
    revenueChange($order, '2026-10-19 10:00:00', 75000, $nmax);

    $vehicles = app(RevenueReport::class)->byVehicle(now()->subDays(5), now());

    expect($vehicles)->toHaveCount(1)
        ->and($vehicles->first())->toMatchArray(['vehicle_id' => $nmax->id, 'base' => 225000, 'delta' => 75000, 'total' => 300000]);
});

it('leaves out differences of orders that are not counted', function () {
    $order = revenueOrder('2026-10-18 09:00:00', 100000, OrderStatus::Refunded, null, TransactionStatus::Cancelled);
    revenueChange($order, '2026-10-19 10:00:00', 50000);
    $this->actingAs(User::factory()->operator()->create());

    $report = app(RevenueReport::class);

    expect($report->daily(now()->subDays(5), now())->sum('total'))->toBe(0)
        ->and($report->changes(now()->subDays(5), now()))->toHaveCount(0);
});

it('filters by vehicle category, moving a cross-category swap to the replacement category', function () {
    $motor = Vehicle::factory()->motorcycle()->create();
    $car = Vehicle::factory()->car()->create();
    revenueOrder('2026-10-18 09:00:00', 100000, OrderStatus::Booking, $motor);
    $swapped = revenueOrder('2026-10-18 10:00:00', 200000, OrderStatus::Booking, $motor);
    revenueChange($swapped, '2026-10-19 10:00:00', 50000, $car);
    $report = app(RevenueReport::class);

    expect($report->daily(now()->subDays(5), now(), VehicleType::Motorcycle)->sum('total'))->toBe(100000)
        ->and($report->daily(now()->subDays(5), now(), VehicleType::Car)->sum('total'))->toBe(250000)
        ->and($report->daily(now()->subDays(5), now())->sum('total'))->toBe(350000)
        ->and($report->byVehicle(now()->subDays(5), now(), VehicleType::Car)->pluck('vehicle_id')->all())->toBe([$car->id])
        ->and($report->changes(now()->subDays(5), now(), VehicleType::Motorcycle))->toHaveCount(0);
});

it('lists the vehicle change differences within the period only', function () {
    $from = Vehicle::factory()->motorcycle()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $to = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX']);
    $order = revenueOrder('2026-10-18 09:00:00', 225000, OrderStatus::Booking, $from);
    $order->update(['code' => 'ABS-REVENUE01']);
    revenueChange($order, '2026-10-19 10:00:00', 75000, $to);
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.revenue.index', ['start_date' => '2026-10-15', 'end_date' => '2026-10-20']))
        ->assertSee('ABS-REVENUE01')
        ->assertSee('Honda Genio')
        ->assertSee('Yamaha NMAX')
        ->assertSee('Rp300.000');

    $this->get(route('dashboard.revenue.index', ['start_date' => '2026-10-20', 'end_date' => '2026-10-20']))
        ->assertDontSee('ABS-REVENUE01')
        ->assertSee('Tidak ada penggantian kendaraan pada periode ini');
});

it('excludes payments outside the chosen period', function () {
    revenueOrder('2026-09-01 09:00:00', 100000);
    revenueOrder('2026-10-19 09:00:00', 40000);

    expect(app(RevenueReport::class)->daily(now()->subDays(5)->startOfDay(), now())->sum('total'))->toBe(40000);
});

it('defaults to the last thirty days and validates the filters', function () {
    revenueOrder('2026-10-01 09:00:00', 70000);
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.revenue.index'))->assertOk()->assertSee('Rp70.000');
    $this->get(route('dashboard.revenue.index', ['start_date' => '2026-10-10', 'end_date' => '2026-10-01']))->assertSessionHasErrors('end_date');
    $this->get(route('dashboard.revenue.index', ['start_date' => '2025-01-01', 'end_date' => '2026-10-20']))->assertSessionHasErrors('end_date');
    $this->get(route('dashboard.revenue.index', ['type' => 'boat']))->assertSessionHasErrors('type');
});

it('counts manual orders like online orders', function () {
    $this->actingAs(User::factory()->operator()->create());
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);
    $this->post(route('dashboard.orders.manual.store'), [
        'start_at' => '2026-10-22T09:00', 'days' => 2, 'items' => [['vehicle_id' => $vehicle->id]],
        'new_customer' => ['name' => 'Budi', 'phone' => '081234567890', 'email' => 'budi@example.test'],
    ])->assertSessionHasNoErrors();

    expect(app(RevenueReport::class)->daily(now()->startOfDay(), now())->sum('total'))->toBe(150000);
});

it('keeps the dashboard revenue chart in line with the revenue menu', function () {
    $order = revenueOrder('2026-10-19 09:00:00', 100000, OrderStatus::Stolen);
    revenueOrder('2026-10-19 10:00:00', 30000, OrderStatus::Refunded, null, TransactionStatus::Cancelled);
    revenueChange($order, '2026-10-20 08:00:00', 20000);
    $this->actingAs(User::factory()->operator()->create());

    $this->getJson(route('dashboard.charts.revenue', ['start_date' => '2026-10-19', 'end_date' => '2026-10-20']))
        ->assertOk()
        ->assertJsonPath('data', [100000, 20000])
        ->assertJsonPath('total', 120000)
        ->assertJsonPath('total_formatted', 'Rp120.000');
});
