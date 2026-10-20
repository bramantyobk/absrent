<?php

use App\Models\EquipmentItem;
use App\Models\User;

function equipmentPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Helm SNI',
        'category' => 'motorcycle',
        'quantity' => 2,
    ];
}

it('rejects guests and customers on every equipment endpoint', function (string $method, string $route, bool $needsItem) {
    $parameters = $needsItem ? [EquipmentItem::factory()->create()] : [];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $parameters))
        ->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.equipment.index', false],
    ['getJson', 'api.v1.dashboard.equipment.show', true],
    ['postJson', 'api.v1.dashboard.equipment.store', false],
    ['putJson', 'api.v1.dashboard.equipment.update', true],
]);

it('lists equipment with filters for staff', function () {
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI']);
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak']);
    EquipmentItem::factory()->car()->inactive()->create(['name' => 'Ban serep']);
    $this->actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.equipment.index'))->assertOk()->assertJsonCount(3, 'data');
    $this->getJson(route('api.v1.dashboard.equipment.index', ['category' => 'car', 'status' => 'active']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dongkrak')
        ->assertJsonPath('data.0.category_label', 'Mobil');
});

it('creates equipment and appends it to the end of its category', function () {
    EquipmentItem::factory()->motorcycle()->create(['sort_order' => 3]);
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.dashboard.equipment.store'), equipmentPayload())
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 4)
        ->assertJsonPath('data.is_active', true);
});

it('validates equipment and rejects a duplicate name in the same category', function () {
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI']);
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.dashboard.equipment.store'), equipmentPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->postJson(route('api.v1.dashboard.equipment.store'), equipmentPayload(['quantity' => 0, 'category' => 'truck']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity', 'category']);
});

it('keeps an item active when an update omits is_active, and deactivates it on request', function () {
    $item = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI']);
    $this->actingAs(User::factory()->operator()->create());

    $this->putJson(route('api.v1.dashboard.equipment.update', $item), equipmentPayload(['quantity' => 4]))
        ->assertOk()
        ->assertJsonPath('data.quantity', 4)
        ->assertJsonPath('data.is_active', true);

    $this->putJson(route('api.v1.dashboard.equipment.update', $item), equipmentPayload(['is_active' => false]))
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('deletes equipment through the api for staff only', function () {
    $item = EquipmentItem::factory()->create();

    $this->deleteJson(route('api.v1.dashboard.equipment.destroy', $item))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->deleteJson(route('api.v1.dashboard.equipment.destroy', $item))->assertForbidden();

    expect($item->fresh())->not->toBeNull();

    $this->actingAs(User::factory()->operator()->create())
        ->deleteJson(route('api.v1.dashboard.equipment.destroy', $item))
        ->assertNoContent();

    expect(EquipmentItem::find($item->id))->toBeNull();
});
