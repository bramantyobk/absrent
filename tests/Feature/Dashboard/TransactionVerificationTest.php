<?php

use App\Models\BankAccount;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
    Storage::fake('local');
});

it('rejects guests and customers on the transaction routes', function (string $method, string $route) {
    $transaction = orderWithTransaction()->transaction;

    $this->{$method}(route($route, $transaction))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}(route($route, $transaction))->assertForbidden();
})->with([
    ['get', 'dashboard.transactions.index'],
    ['get', 'dashboard.transactions.show'],
    ['post', 'dashboard.transactions.verify'],
]);

it('lists verifying transactions first and flags those about to expire', function () {
    orderWithTransaction(['code' => 'ABS-DONE0001', 'status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);
    orderWithTransaction(['code' => 'ABS-SOON0002', 'expires_at' => now()->addHours(2)]);
    orderWithTransaction(['code' => 'ABS-LATER003', 'expires_at' => now()->addHours(20)]);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.transactions.index'))
        ->assertOk()
        ->assertSeeInOrder(['ABS-LATER003', 'ABS-SOON0002', 'ABS-DONE0001'])
        ->assertSee('Hampir habis');

    $this->get(route('dashboard.transactions.index', ['status' => 'paid']))->assertSee('ABS-DONE0001')->assertDontSee('ABS-SOON0002');
    $this->get(route('dashboard.transactions.index', ['search' => 'LATER']))->assertSee('ABS-LATER003')->assertDontSee('ABS-SOON0002');
});

it('shows the verification form only while awaiting verification', function () {
    $verifying = orderWithTransaction(['code' => 'ABS-VERIFY01'])->transaction;
    $paid = orderWithTransaction(['code' => 'ABS-PAID0002', 'status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid])->transaction;
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.transactions.show', $verifying))->assertOk()->assertSee('Simpan verifikasi')->assertSee('Rp300.000');
    $this->get(route('dashboard.transactions.show', $paid))->assertOk()->assertDontSee('Simpan verifikasi')->assertSee('sudah tidak menunggu verifikasi');
});

it('marks a transaction paid with a proof, records the verifier, and moves the order to booking', function () {
    $operator = User::factory()->operator()->create();
    $order = orderWithTransaction();
    $proof = UploadedFile::fake()->image('bukti.jpg');

    $this->actingAs($operator)
        ->post(route('dashboard.transactions.verify', $order->transaction), ['status' => 'paid', 'proof' => $proof])
        ->assertRedirect(route('dashboard.transactions.show', $order->transaction))
        ->assertSessionHasNoErrors();

    $order->refresh()->load('transaction');

    expect($order->status)->toBe(OrderStatus::Booking)
        ->and($order->expires_at)->toBeNull()
        ->and($order->transaction->status)->toBe(TransactionStatus::Paid)
        ->and($order->transaction->verified_by)->toBe($operator->id)
        ->and($order->transaction->verified_at->toDateTimeString())->toBe('2026-10-12 10:00:00');

    Storage::disk('local')->assertExists($order->transaction->proof_path);
    expect($order->transaction->proof_path)->toStartWith('payment-proofs/');
});

it('keeps the vehicle held after paying and releases it after a failed verification', function () {
    $paidVehicle = Vehicle::factory()->create();
    $failedVehicle = Vehicle::factory()->create();
    $paid = orderWithTransaction(vehicle: $paidVehicle);
    $failed = orderWithTransaction(vehicle: $failedVehicle);
    $paid->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-21 08:00:00']);
    $failed->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-21 08:00:00']);
    $free = fn (Vehicle $vehicle): bool => Vehicle::query()->availableBetween(now()->setDate(2026, 10, 20)->setTime(8, 0), now()->setDate(2026, 10, 21)->setTime(8, 0))->whereKey($vehicle->id)->exists();
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.transactions.verify', $paid->transaction), ['status' => 'paid', 'proof' => UploadedFile::fake()->image('a.png')]);
    $this->post(route('dashboard.transactions.verify', $failed->transaction), ['status' => 'failed', 'note' => 'Dana tidak ditemukan di mutasi.']);

    expect($free($paidVehicle))->toBeFalse()->and($free($failedVehicle))->toBeTrue();
});

it('marks a transaction failed with a note, which is final for the order', function () {
    $operator = User::factory()->admin()->create();
    $order = orderWithTransaction();

    $this->actingAs($operator)
        ->post(route('dashboard.transactions.verify', $order->transaction), ['status' => 'failed', 'note' => 'Dana tidak ditemukan di mutasi.'])
        ->assertSessionHasNoErrors();

    $order->refresh()->load('transaction');

    expect($order->status)->toBe(OrderStatus::Failed)
        ->and($order->transaction->status)->toBe(TransactionStatus::Failed)
        ->and($order->transaction->note)->toBe('Dana tidak ditemukan di mutasi.')
        ->and($order->transaction->proof_path)->toBeNull()
        ->and($order->transaction->verified_by)->toBe($operator->id);

    $this->actingAs(User::findOrFail($order->user_id))
        ->post(route('orders.confirm', $order), ['bank_account_id' => BankAccount::factory()->create()->id])
        ->assertForbidden();
});

it('lets staff correct the bank account chosen by the customer', function () {
    $order = orderWithTransaction();
    $correct = BankAccount::factory()->create(['bank_name' => 'BNI']);

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.transactions.verify', $order->transaction), ['status' => 'paid', 'proof' => UploadedFile::fake()->image('a.jpg'), 'bank_account_id' => $correct->id]);

    expect($order->transaction->refresh()->bank_account_id)->toBe($correct->id);
});

it('validates the verification input', function (array $input, string $field) {
    $order = orderWithTransaction();

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.transactions.verify', $order->transaction), $input)
        ->assertSessionHasErrors($field);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->transaction->status)->toBe(TransactionStatus::Verifying);
})->with([
    'no decision' => [[], 'status'],
    'invalid decision' => [['status' => 'verifying'], 'status'],
    'paid without proof' => [['status' => 'paid'], 'proof'],
    'proof is not an image' => [['status' => 'paid', 'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')], 'proof'],
    'proof over 2 MB' => [['status' => 'paid', 'proof' => UploadedFile::fake()->image('besar.jpg')->size(2049)], 'proof'],
    'failed without a note' => [['status' => 'failed'], 'note'],
    'unknown bank account' => [['status' => 'failed', 'note' => 'x', 'bank_account_id' => 9999], 'bank_account_id'],
]);

it('only verifies transactions that await verification', function (TransactionStatus $status) {
    $order = orderWithTransaction(['status' => OrderStatus::Pending], ['status' => $status]);

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.transactions.verify', $order->transaction), ['status' => 'paid', 'proof' => UploadedFile::fake()->image('a.jpg')])
        ->assertSessionHasErrors('status');

    expect($order->transaction->refresh()->status)->toBe($status);
})->with([TransactionStatus::Pending, TransactionStatus::Paid, TransactionStatus::Failed, TransactionStatus::Cancelled]);

it('serves the proof to staff and the order owner only', function () {
    $owner = User::factory()->create();
    $order = orderWithTransaction(['user_id' => $owner->id, 'status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid, 'proof_path' => 'payment-proofs/bukti.jpg']);
    Storage::disk('local')->put('payment-proofs/bukti.jpg', 'isi-bukti');

    $this->actingAs(User::factory()->operator()->create())->get(route('dashboard.transactions.proof', $order->transaction))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($owner)->get(route('orders.proof', $order))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('orders.proof', $order))->assertNotFound();
    $this->actingAs($owner)->get(route('dashboard.transactions.proof', $order->transaction))->assertForbidden();
    auth()->logout();
    $this->get(route('orders.proof', $order))->assertRedirect(route('login'));
});

it('returns 404 for a proof that does not exist yet or whose file is gone', function () {
    $owner = User::factory()->create();
    $withoutProof = orderWithTransaction(['user_id' => $owner->id]);
    $missingFile = orderWithTransaction(['user_id' => $owner->id], ['proof_path' => 'payment-proofs/hilang.jpg']);

    $this->actingAs($owner)->get(route('orders.proof', $withoutProof))->assertNotFound();
    $this->get(route('orders.proof', $missingFile))->assertNotFound();
});
