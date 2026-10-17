<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLookupRequest;
use App\Http\Resources\CustomerLookupResource;
use App\Services\CustomerLookup;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerLookupController extends Controller
{
    /**
     * Cari pelanggan
     *
     * Untuk pesanan manual: `phone` dicari lebih dulu (dinormalkan ke 62..., yang cocok persis tampil paling atas), lalu `q` untuk email atau nama
     * (minimal 2 karakter). Salah satu wajib diisi. Hanya pelanggan (role `user`), maksimal 10 hasil. `phone_flagged` menandai nomor yang
     * perlu ditinjau.
     */
    public function __invoke(CustomerLookupRequest $request, CustomerLookup $customerLookup): AnonymousResourceCollection
    {
        return CustomerLookupResource::collection($customerLookup->search($request->validated('phone'), $request->validated('q')));
    }
}
