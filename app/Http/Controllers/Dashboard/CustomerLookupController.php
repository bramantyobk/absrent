<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLookupRequest;
use App\Http\Resources\CustomerLookupResource;
use App\Services\CustomerLookup;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerLookupController extends Controller
{
    public function __invoke(CustomerLookupRequest $request, CustomerLookup $customerLookup): AnonymousResourceCollection
    {
        return CustomerLookupResource::collection($customerLookup->search($request->validated('phone'), $request->validated('q')));
    }
}
