<?php

use App\InspectionType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

it('rejects guests, customers, and operators on the inspection edit endpoint', function () {
    ['return' => $return] = inspectedOrder();
    $url = route('api.v1.dashboard.inspections.update', $return);

    $this->patchJson($url, editPayload())->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->patchJson($url, editPayload())->assertForbidden();
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->patchJson($url, editPayload())->assertForbidden();

    expect($return->refresh()->odometer_km)->toBe(11800);
});

it('edits an inspection through the api without exposing the editor stamp', function () {
    ['vehicle' => $vehicle, 'return' => $return] = inspectedOrder();
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->patchJson(route('api.v1.dashboard.inspections.update', $return), editPayload(['equipment' => [1 => 2, 2 => 1]]))
        ->assertOk()
        ->assertJsonPath('data.odometer_km', 1180)
        ->assertJsonPath('data.type', 'return')
        ->assertJsonPath('data.equipment_checks.0.checked', 2)
        ->assertJsonMissingPath('data.edited_by')
        ->assertJsonMissingPath('data.edited_at');

    expect(array_keys($response->json('data')))->not->toContain('edited_by', 'edited_at')
        ->and($return->refresh()->edited_by)->toBe($admin->id)
        ->and($vehicle->refresh()->odometer_km)->toBe(1180);

    $this->getJson(route('api.v1.dashboard.orders.show', $return->orderItem->order_id))
        ->assertJsonMissingPath('data.items.0.inspections.0.edited_by')
        ->assertJsonMissingPath('data.items.0.inspections.1.edited_at');
});

it('validates the edit and refuses manual inspections through the api', function () {
    ['pickup' => $pickup] = inspectedOrder();
    $manual = VehicleInspection::factory()->create(['vehicle_id' => Vehicle::factory()->create()->id, 'type' => InspectionType::Manual, 'odometer_km' => 500]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->patchJson(route('api.v1.dashboard.inspections.update', $pickup), ['odometer_km' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['odometer_km', 'energy_level']);
    $this->patchJson(route('api.v1.dashboard.inspections.update', $pickup), editPayload(['odometer_km' => 20000]))->assertUnprocessable()->assertJsonValidationErrors('odometer_km');
    $this->patchJson(route('api.v1.dashboard.inspections.update', $manual), ['odometer_km' => 400, 'energy_level' => 10])->assertUnprocessable()->assertJsonValidationErrors('inspection');
});
