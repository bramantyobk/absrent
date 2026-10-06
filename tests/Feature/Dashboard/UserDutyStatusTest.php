<?php

use App\Models\User;

it('lets an admin switch an operator on and off duty', function () {
    $this->actingAs(User::factory()->admin()->create());
    $operator = User::factory()->operator()->create();

    $this->patch(route('dashboard.users.duty-status', $operator), ['is_on_duty' => true])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($operator->refresh()->is_on_duty)->toBeTrue();

    $this->patch(route('dashboard.users.duty-status', $operator), ['is_on_duty' => false]);

    expect($operator->refresh()->is_on_duty)->toBeFalse();
});

it('rejects duty status for non-operators and inactive operators', function (callable $makeTarget) {
    $this->actingAs(User::factory()->admin()->create());
    $target = $makeTarget();

    $this->patch(route('dashboard.users.duty-status', $target), ['is_on_duty' => true])
        ->assertSessionHasErrors('is_on_duty');

    expect($target->refresh()->is_on_duty)->toBeFalse();
})->with([
    'customer' => [fn () => User::factory()->create()],
    'admin' => [fn () => User::factory()->admin()->create()],
    'inactive operator' => [fn () => User::factory()->operator()->inactive()->create()],
]);

it('requires a boolean duty flag', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.users.duty-status', User::factory()->operator()->create()), ['is_on_duty' => 'maybe'])
        ->assertSessionHasErrors('is_on_duty');
});

it('does not let an operator change duty status, including their own', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->patch(route('dashboard.users.duty-status', $operator), ['is_on_duty' => true])
        ->assertForbidden();
});

it('answers with JSON when the request expects JSON', function () {
    $this->actingAs(User::factory()->admin()->create());
    $operator = User::factory()->operator()->create(['name' => 'Rina']);

    $this->patchJson(route('dashboard.users.duty-status', $operator), ['is_on_duty' => true])
        ->assertOk()
        ->assertExactJson(['is_on_duty' => true, 'message' => 'Rina sekarang bertugas.']);

    $this->patchJson(route('dashboard.users.duty-status', $operator), ['is_on_duty' => false])
        ->assertOk()
        ->assertExactJson(['is_on_duty' => false, 'message' => 'Rina tidak lagi bertugas.']);

    expect($operator->refresh()->is_on_duty)->toBeFalse();
});

it('accepts true and false as text, as sent by FormData', function (string $value, bool $expected) {
    $this->actingAs(User::factory()->admin()->create());
    $operator = User::factory()->operator()->onDuty()->create();

    $this->patchJson(route('dashboard.users.duty-status', $operator), ['is_on_duty' => $value])
        ->assertOk()
        ->assertJsonPath('is_on_duty', $expected);
})->with([
    ['true', true],
    ['false', false],
    ['1', true],
    ['0', false],
]);

it('returns JSON validation errors for invalid duty requests', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->patchJson(route('dashboard.users.duty-status', User::factory()->create()), ['is_on_duty' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_on_duty');

    $this->patchJson(route('dashboard.users.duty-status', User::factory()->operator()->create()), ['is_on_duty' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_on_duty');
});

it('returns 403 as JSON for operators', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->patchJson(route('dashboard.users.duty-status', $operator), ['is_on_duty' => true])
        ->assertForbidden();
});
