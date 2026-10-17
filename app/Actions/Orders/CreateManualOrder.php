<?php

namespace App\Actions\Orders;

use App\Actions\Transactions\VerifyTransaction;
use App\Models\Order;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateManualOrder
{
    public function __construct(private CreateOrder $createOrder) {}

    /**
     * Booking langsung oleh staf untuk pelanggan di kantor, dalam satu proses: memakai pelanggan yang ada atau membuat pelanggan baru
     * (role `user`, password acak yang tidak diberikan), lalu membuat pesanan Lunas berstatus `Booking` lewat aturan yang sama dengan
     * pesanan online. Bila nomor pelanggan baru sama dengan pelanggan lama yang namanya berbeda, pelanggan lama ditandai
     * (`phone_flagged_at`) karena pemiliknya kemungkinan sudah berganti nomor. Nomor dan nama yang sama dianggap pelanggan yang sama
     * dan ditolak agar tidak terbentuk akun ganda. Bila pembuatan pesanan gagal, akun dan penanda ikut dibatalkan.
     *
     * @param  array{start_at: string, days: int|string, items: list<array{vehicle_id: int|string}>, customer_id?: int|string|null, new_customer?: array{name: string, phone: string, email: string}|null, bank_account_id?: int|string|null, payment_note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $staff, array $data, ?UploadedFile $proof = null): Order
    {
        $proofPath = $proof?->store(VerifyTransaction::PROOF_DIRECTORY);

        try {
            return DB::transaction(function () use ($staff, $data, $proofPath): Order {
                $customer = filled($data['customer_id'] ?? null)
                    ? $this->existingCustomer((int) $data['customer_id'])
                    : $this->newCustomer($data['new_customer'] ?? []);

                return $this->createOrder->handleForStaff(
                    $customer,
                    $staff,
                    $data['start_at'],
                    (int) $data['days'],
                    array_values(array_map(fn (array $item): int => (int) $item['vehicle_id'], $data['items'])),
                    [
                        'bank_account_id' => filled($data['bank_account_id'] ?? null) ? (int) $data['bank_account_id'] : null,
                        'proof_path' => $proofPath,
                        'note' => $data['payment_note'] ?? null,
                    ],
                )->load(['user', 'creator', 'items.vehicle', 'transaction']);
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function existingCustomer(int $id): User
    {
        $customer = User::query()->whereKey($id)->where('role', UserRole::User)->first();

        if (! $customer || ! $customer->is_active) {
            throw ValidationException::withMessages(['customer_id' => 'Pelanggan tidak ditemukan atau tidak aktif.']);
        }

        return $customer;
    }

    /**
     * @param  array{name?: string, phone?: string, email?: string}  $input
     *
     * @throws ValidationException
     */
    private function newCustomer(array $input): User
    {
        $name = trim((string) ($input['name'] ?? ''));
        $phone = User::normalizePhone($input['phone'] ?? null);

        $sharingPhone = User::query()->where('role', UserRole::User)->where('phone', $phone)->lockForUpdate()->get();

        if ($same = $sharingPhone->first(fn (User $user): bool => Str::lower($user->name) === Str::lower($name))) {
            throw ValidationException::withMessages([
                'new_customer.phone' => "Pelanggan {$same->name} dengan nomor ini sudah terdaftar ({$same->email}). Pilih pelanggan itu, jangan buat akun baru.",
            ]);
        }

        User::query()->whereIn('id', $sharingPhone->pluck('id'))->whereNull('phone_flagged_at')->update(['phone_flagged_at' => now()]);

        return User::create([
            'name' => $name,
            'email' => $input['email'],
            'phone' => $phone,
            'password' => Str::random(40),
            'role' => UserRole::User,
            'is_active' => true,
        ]);
    }
}
