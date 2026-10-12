<?php

use App\Models\EquipmentItem;
use App\Models\User;
use App\Models\Vehicle;
use App\VehicleType;

function validEquipmentData(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Helm SNI',
        'category' => 'motorcycle',
        'quantity' => '2',
        'is_active' => '1',
    ];
}

it('requires a staff role for every equipment action', function (string $method, string $route) {
    $item = EquipmentItem::factory()->create();

    $this->{$method}(route($route, $item))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $item))
        ->assertForbidden();
})->with([
    ['get', 'dashboard.equipment.index'],
    ['get', 'dashboard.equipment.create'],
    ['get', 'dashboard.equipment.edit'],
    ['post', 'dashboard.equipment.store'],
    ['put', 'dashboard.equipment.update'],
]);

it('lists equipment with search, category, and status filters ordered within each category', function () {
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak', 'sort_order' => 2]);
    EquipmentItem::factory()->car()->create(['name' => 'Ban serep', 'sort_order' => 1]);
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'sort_order' => 1]);
    EquipmentItem::factory()->motorcycle()->inactive()->create(['name' => 'Jas hujan', 'sort_order' => 2]);

    $this->actingAs(User::factory()->operator()->create());

    $names = fn (array $query) => $this->get(route('dashboard.equipment.index', $query))
        ->assertOk()
        ->viewData('items')
        ->pluck('name')
        ->all();

    expect($names([]))->toBe(['Ban serep', 'Dongkrak', 'Helm SNI', 'Jas hujan'])
        ->and($names(['search' => 'helm']))->toBe(['Helm SNI'])
        ->and($names(['category' => 'car']))->toBe(['Ban serep', 'Dongkrak'])
        ->and($names(['status' => 'inactive']))->toBe(['Jas hujan'])
        ->and($names(['status' => 'active', 'category' => 'motorcycle']))->toBe(['Helm SNI']);
});

it('paginates equipment by ten', function () {
    EquipmentItem::factory()->count(12)->create();

    $items = $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.equipment.index'))
        ->viewData('items');

    expect($items)->toHaveCount(10)->and($items->total())->toBe(12);
});

it('lets an operator create equipment with a normalized name and an explicit order', function () {
    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.equipment.store'), validEquipmentData(['name' => '  Helm   SNI ', 'sort_order' => '5']))
        ->assertRedirect(route('dashboard.equipment.index'))
        ->assertSessionHasNoErrors();

    $item = EquipmentItem::sole();

    expect($item->name)->toBe('Helm SNI')
        ->and($item->category)->toBe(VehicleType::Motorcycle)
        ->and($item->quantity)->toBe(2)
        ->and($item->is_active)->toBeTrue()
        ->and($item->sort_order)->toBe(5);
});

it('puts a new item without an order at the end of its own category', function () {
    EquipmentItem::factory()->motorcycle()->create(['sort_order' => 7]);
    EquipmentItem::factory()->car()->create(['sort_order' => 2]);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.equipment.store'), validEquipmentData(['name' => 'Jas hujan', 'sort_order' => '']));
    $this->post(route('dashboard.equipment.store'), validEquipmentData(['name' => 'Kotak P3K', 'category' => 'car']));

    expect(EquipmentItem::where('name', 'Jas hujan')->value('sort_order'))->toBe(8)
        ->and(EquipmentItem::where('name', 'Kotak P3K')->value('sort_order'))->toBe(3);
});

it('validates equipment input', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.equipment.store'), validEquipmentData($overrides))
        ->assertSessionHasErrors($field);

    expect(EquipmentItem::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown category' => [['category' => 'truck'], 'category'],
    'zero quantity' => [['quantity' => '0'], 'quantity'],
    'huge quantity' => [['quantity' => '100'], 'quantity'],
    'negative order' => [['sort_order' => '-1'], 'sort_order'],
]);

it('rejects a duplicate name within the same category but allows it in another', function () {
    EquipmentItem::factory()->motorcycle()->create(['name' => 'STNK']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.equipment.store'), validEquipmentData(['name' => 'STNK']))
        ->assertSessionHasErrors('name');

    $this->post(route('dashboard.equipment.store'), validEquipmentData(['name' => 'STNK', 'category' => 'car']))
        ->assertSessionHasNoErrors();

    expect(EquipmentItem::where('name', 'STNK')->count())->toBe(2);
});

it('updates equipment, keeping its own name and its order when left blank', function () {
    $item = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 1, 'sort_order' => 4]);

    $this->actingAs(User::factory()->operator()->create())
        ->put(route('dashboard.equipment.update', $item), validEquipmentData(['quantity' => '3', 'sort_order' => '']))
        ->assertRedirect(route('dashboard.equipment.index'))
        ->assertSessionHasNoErrors();

    expect($item->refresh())->quantity->toBe(3)->sort_order->toBe(4);
});

it('rejects renaming equipment to a name that exists in its category', function () {
    EquipmentItem::factory()->motorcycle()->create(['name' => 'STNK']);
    $item = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.equipment.update', $item), validEquipmentData(['name' => 'STNK']))
        ->assertSessionHasErrors('name');
});

it('deactivates equipment instead of deleting it', function () {
    $item = EquipmentItem::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->delete("/dashboard/equipment/{$item->id}")->assertStatus(405);

    $this->put(route('dashboard.equipment.update', $item), validEquipmentData(['name' => $item->name, 'is_active' => '0']))
        ->assertSessionHasNoErrors();

    expect($item->fresh()->is_active)->toBeFalse();
});

it('lists only active equipment of the vehicle category on the vehicle detail', function () {
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'sort_order' => 2]);
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Jas hujan', 'sort_order' => 1]);
    EquipmentItem::factory()->motorcycle()->inactive()->create(['name' => 'Kunci cadangan']);
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak']);
    $vehicle = Vehicle::factory()->motorcycle()->create();

    $response = $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSeeInOrder(['Jas hujan', 'Helm SNI'])
        ->assertDontSee('Kunci cadangan')
        ->assertDontSee('Dongkrak');

    expect($response->viewData('equipment')->pluck('name')->all())->toBe(['Jas hujan', 'Helm SNI']);
});

it('shows an empty state on the vehicle detail when the category has no equipment', function () {
    $vehicle = Vehicle::factory()->car()->create();

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.vehicles.show', $vehicle))
        ->assertSee('Belum ada perlengkapan aktif untuk kategori Mobil.');
});
