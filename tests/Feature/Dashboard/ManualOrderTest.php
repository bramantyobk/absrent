<?php

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
    Storage::fake('local');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function manualPayload(Vehicle $vehicle, array $overrides = []): array
{
    return $overrides + [
        'start_at' => '2026-10-14T09:00',
        'days' => 2,
        'items' => [['vehicle_id' => $vehicle->id]],
        'new_customer' => ['name' => 'Budi Santoso', 'phone' => '081234567890', 'email' => 'budi@example.test'],
    ];
}

it('rejects guests and customers on the manual order routes', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}(route($route))->assertForbidden();
})->with([
    ['get', 'dashboard.orders.manual.create'],
    ['post', 'dashboard.orders.manual.store'],
    ['get', 'dashboard.customers.lookup'],
]);

it('shows the period form, and only vehicles free for the period once it is chosen', function () {
    Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 1111 AAA']);
    $taken = Vehicle::factory()->create(['brand' => 'Suzuki', 'model' => 'Satria', 'plate_number' => 'B 2222 BBB']);
    Vehicle::factory()->unavailable()->create(['plate_number' => 'B 3333 CCC']);
    holdVehicle($taken, OrderStatus::Booking, '2026-10-14 08:00:00', '2026-10-15 08:00:00');
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.orders.manual.create'))->assertOk()->assertSee('Cari unit kosong')->assertDontSee('B 1111 AAA');

    $this->get(route('dashboard.orders.manual.create', ['start_at' => '2026-10-14T09:00', 'days' => 2]))
        ->assertOk()
        ->assertSee('B 1111 AAA')
        ->assertDontSee('B 2222 BBB')
        ->assertDontSee('B 3333 CCC');

    $this->get(route('dashboard.orders.manual.create', ['start_at' => '2026-10-11T09:00', 'days' => 2]))->assertSessionHasErrors('start_at');
});

it('warns outside office hours without blocking', function () {
    $this->actingAs(User::factory()->operator()->create());

    $this->travelTo('2026-10-12 10:00:00');
    $this->get(route('dashboard.orders.manual.create'))->assertDontSee('di luar jam layanan kantor');

    $this->travelTo('2026-10-12 20:00:00');
    $this->get(route('dashboard.orders.manual.create'))->assertOk()->assertSee('di luar jam layanan kantor');

    $this->travelTo('2026-10-18 10:00:00');
    $this->get(route('dashboard.orders.manual.create'))->assertOk()->assertSee('di luar jam layanan kantor');
});

it('creates a paid booking for an existing customer, recorded by the staff member', function () {
    $staff = User::factory()->operator()->create();
    $customer = User::factory()->create(['name' => 'Siti Aminah']);
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);
    $bank = BankAccount::factory()->create();

    $this->actingAs($staff)
        ->post(route('dashboard.orders.manual.store'), manualPayload($vehicle, ['customer_id' => $customer->id, 'new_customer' => null, 'bank_account_id' => $bank->id, 'payment_note' => 'Transfer di kantor']))
        ->assertSessionHasNoErrors();

    $order = Order::sole()->load(['transaction', 'items']);

    expect($order->user_id)->toBe($customer->id)
        ->and($order->created_by)->toBe($staff->id)
        ->and($order->isManual())->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Booking)
        ->and($order->expires_at)->toBeNull()
        ->and($order->total_price)->toBe(150000)
        ->and($order->items->sole()->vehicle_id)->toBe($vehicle->id)
        ->and($order->items->sole()->start_at->toDateTimeString())->toBe('2026-10-14 09:00:00')
        ->and($order->items->sole()->end_at->toDateTimeString())->toBe('2026-10-16 09:00:00')
        ->and($order->transaction->status)->toBe(TransactionStatus::Paid)
        ->and($order->transaction->amount)->toBe(150000)
        ->and($order->transaction->verified_by)->toBe($staff->id)
        ->and($order->transaction->verified_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($order->transaction->bank_account_id)->toBe($bank->id)
        ->and($order->transaction->note)->toBe('Transfer di kantor')
        ->and($order->transaction->proof_path)->toBeNull()
        ->and(User::where('role', UserRole::User)->count())->toBe(1);

    $isHeld = ! Vehicle::query()->availableBetween($order->items->first()->start_at, $order->items->first()->end_at)->whereKey($vehicle->id)->exists();

    expect($isHeld)->toBeTrue();
});

it('stores an optional proof privately and rejects a non-image proof', function () {
    $customer = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create(), ['customer_id' => $customer->id, 'new_customer' => null, 'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('proof');

    expect(Order::count())->toBe(0);

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create(), ['customer_id' => $customer->id, 'new_customer' => null, 'proof' => UploadedFile::fake()->image('bukti.png')]))
        ->assertSessionHasNoErrors();

    $path = Order::sole()->transaction->proof_path;

    expect($path)->toStartWith('payment-proofs/');
    Storage::disk('local')->assertExists($path);
});

it('is not blocked by the pending order limit', function () {
    $customer = User::factory()->create();
    Order::factory()->count(3)->for($customer)->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create(), ['customer_id' => $customer->id, 'new_customer' => null]))
        ->assertSessionHasNoErrors();

    expect(Order::where('status', OrderStatus::Booking)->count())->toBe(1);
});

it('creates a new customer with a random password and the user role only', function () {
    $staff = User::factory()->operator()->create();
    $this->actingAs($staff);

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()))->assertSessionHasNoErrors();

    $customer = User::where('email', 'budi@example.test')->sole();

    expect($customer->role)->toBe(UserRole::User)
        ->and($customer->name)->toBe('Budi Santoso')
        ->and($customer->phone)->toBe('6281234567890')
        ->and($customer->is_active)->toBeTrue()
        ->and($customer->phone_flagged_at)->toBeNull()
        ->and(Hash::check('password', $customer->password))->toBeFalse()
        ->and(Order::sole()->user_id)->toBe($customer->id)
        ->and(Order::sole()->created_by)->toBe($staff->id);
});

it('rejects a new customer with the same phone and name as an existing one', function () {
    User::factory()->create(['name' => 'budi santoso', 'phone' => '6281234567890', 'email' => 'lama@example.test']);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()))->assertSessionHasErrors('new_customer.phone');

    expect(User::where('role', UserRole::User)->count())->toBe(1)->and(Order::count())->toBe(0);
});

it('flags the old customer when a new customer reuses the phone under another name', function () {
    $old = User::factory()->create(['name' => 'Andi Lama', 'phone' => '6281234567890']);
    $staffWithSamePhone = User::factory()->operator()->create(['phone' => '6281234567890']);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()))->assertSessionHasNoErrors();

    expect($old->refresh()->phone_flagged_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($staffWithSamePhone->refresh()->phone_flagged_at)->toBeNull()
        ->and(User::where('email', 'budi@example.test')->exists())->toBeTrue();

    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard.users.index'))->assertSee('Nomor perlu ditinjau')->assertSee('Sudah ditinjau');
});

it('rolls back the account, the flag, and the proof when the order cannot be created', function () {
    $old = User::factory()->create(['name' => 'Andi Lama', 'phone' => '6281234567890']);
    $taken = Vehicle::factory()->create();
    holdVehicle($taken, OrderStatus::Booking, '2026-10-14 08:00:00', '2026-10-16 08:00:00');
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload($taken, ['proof' => UploadedFile::fake()->image('bukti.jpg')]))->assertSessionHasErrors();

    expect(User::where('email', 'budi@example.test')->exists())->toBeFalse()
        ->and($old->refresh()->phone_flagged_at)->toBeNull()
        ->and(Order::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('payment-proofs'))->toBe([]);
});

it('applies the online rules: bookings in the past, conflicts, the two vehicle limit, and duplicates are rejected', function () {
    $customer = User::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->operator()->create());
    $payload = fn (array $overrides = []) => manualPayload($vehicle, ['customer_id' => $customer->id, 'new_customer' => null] + $overrides);

    $this->post(route('dashboard.orders.manual.store'), $payload(['start_at' => '2026-10-11T09:00']))->assertSessionHasErrors('start_at');
    $this->post(route('dashboard.orders.manual.store'), $payload(['days' => 8]))->assertSessionHasErrors('items.0.vehicle_id');
    $this->post(route('dashboard.orders.manual.store'), $payload(['items' => [['vehicle_id' => $vehicle->id], ['vehicle_id' => $vehicle->id]]]))->assertSessionHasErrors();
    $this->post(route('dashboard.orders.manual.store'), $payload(['items' => [['vehicle_id' => $vehicle->id], ['vehicle_id' => Vehicle::factory()->create()->id], ['vehicle_id' => Vehicle::factory()->create()->id]]]))->assertSessionHasErrors('items');

    $this->post(route('dashboard.orders.manual.store'), $payload())->assertSessionHasNoErrors();
    $this->post(route('dashboard.orders.manual.store'), $payload(['start_at' => '2026-10-15T09:00']))->assertSessionHasErrors('items.0.vehicle_id');

    expect(Order::count())->toBe(1);
});

it('validates the customer choice and new customer data', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'ada@example.test']);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create(), $overrides))->assertSessionHasErrors($field);

    expect(Order::count())->toBe(0);
})->with([
    'no customer at all' => [['new_customer' => null], 'new_customer'],
    'missing name' => [['new_customer' => ['name' => '', 'phone' => '081234567890', 'email' => 'a@example.test']], 'new_customer.name'],
    'invalid phone' => [['new_customer' => ['name' => 'A', 'phone' => '123', 'email' => 'a@example.test']], 'new_customer.phone'],
    'missing email' => [['new_customer' => ['name' => 'A', 'phone' => '081234567890', 'email' => '']], 'new_customer.email'],
    'duplicate email' => [['new_customer' => ['name' => 'A', 'phone' => '081234567890', 'email' => 'ada@example.test']], 'new_customer.email'],
]);

it('rejects staff, inactive, and unknown users as the existing customer', function () {
    $this->actingAs(User::factory()->operator()->create());
    $vehicle = Vehicle::factory()->create();

    foreach ([User::factory()->admin()->create()->id, User::factory()->inactive()->create()->id] as $id) {
        $this->post(route('dashboard.orders.manual.store'), manualPayload($vehicle, ['customer_id' => $id, 'new_customer' => null]))->assertSessionHasErrors('customer_id');
    }

    $this->post(route('dashboard.orders.manual.store'), manualPayload($vehicle, ['customer_id' => 99999, 'new_customer' => null]))->assertSessionHasErrors('customer_id');

    expect(Order::count())->toBe(0);
});

it('lets operators create customer accounts only through this form, not manage users', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator);

    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()))->assertSessionHasNoErrors();
    $this->get(route('dashboard.users.create'))->assertForbidden();
    $this->patch(route('dashboard.users.phone-review', User::where('email', 'budi@example.test')->first()))->assertForbidden();
});

it('searches customers by phone first, then email or name, customers only', function () {
    $byPhone = User::factory()->create(['name' => 'Zed Pemilik Nomor', 'phone' => '6281234567890']);
    $byName = User::factory()->create(['name' => 'Budi Pencari', 'phone' => '6281111111111', 'email' => 'budi.p@example.test']);
    User::factory()->operator()->create(['name' => 'Budi Operator', 'phone' => '6281234567890']);
    $this->actingAs(User::factory()->operator()->create());

    $this->getJson(route('dashboard.customers.lookup', ['phone' => '081234567890']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $byPhone->id)
        ->assertJsonPath('data.0.phone_flagged', false);

    $this->getJson(route('dashboard.customers.lookup', ['q' => 'Budi']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $byName->id);
    $this->getJson(route('dashboard.customers.lookup', ['q' => 'budi.p@example']))->assertJsonPath('data.0.id', $byName->id);
    $this->getJson(route('dashboard.customers.lookup', ['phone' => '081234567890', 'q' => 'Budi']))->assertJsonPath('data.0.id', $byPhone->id)->assertJsonCount(2, 'data');
    $this->getJson(route('dashboard.customers.lookup'))->assertUnprocessable();
    $this->getJson(route('dashboard.customers.lookup', ['q' => 'a']))->assertUnprocessable();
    $this->getJson(route('dashboard.customers.lookup', ['phone' => '089999999999']))->assertOk()->assertJsonCount(0, 'data');
});

it('clears the phone flag when an admin changes the number or marks it reviewed', function () {
    $flagged = User::factory()->create(['phone' => '6281234567890', 'phone_flagged_at' => now()]);
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.users.phone-review', $flagged))->assertRedirect();
    expect($flagged->refresh()->phone_flagged_at)->toBeNull();

    $flagged->update(['phone_flagged_at' => now()]);
    $this->put(route('dashboard.users.update', $flagged), ['name' => $flagged->name, 'email' => $flagged->email, 'phone' => '082233445566', 'role' => 'user', 'is_active' => '1', 'is_on_duty' => '0'])
        ->assertSessionHasNoErrors();
    expect($flagged->refresh()->phone_flagged_at)->toBeNull()->and($flagged->phone)->toBe('6282233445566');

    $flagged->update(['phone_flagged_at' => now()]);
    $this->put(route('dashboard.users.update', $flagged), ['name' => 'Nama Baru', 'email' => $flagged->email, 'phone' => '082233445566', 'role' => 'user', 'is_active' => '1', 'is_on_duty' => '0']);
    expect($flagged->refresh()->phone_flagged_at)->not->toBeNull();
});

it('marks manual orders on the list and detail, and keeps the staff who created them from being deleted', function () {
    $staff = User::factory()->operator()->create(['name' => 'Siti Operator']);
    $this->actingAs($staff);
    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()));
    $order = Order::sole();
    orderWithTransaction(['code' => 'ABS-ONLINE001']);

    $this->get(route('dashboard.orders.index'))->assertSee('Manual')->assertSee('Buat pesanan manual');
    $this->get(route('dashboard.orders.show', $order))->assertSee('Pesanan manual oleh Siti Operator');
    expect($staff->hasHistory())->toBeTrue();
});

it('counts manual orders like online orders on the dashboard totals', function () {
    $this->actingAs(User::factory()->operator()->create());
    $this->post(route('dashboard.orders.manual.store'), manualPayload(Vehicle::factory()->create()));

    $this->get(route('dashboard.orders.index', ['status' => 'booking']))->assertSee(Order::sole()->code);
    expect(Order::sole()->transaction->status)->toBe(TransactionStatus::Paid);
});
