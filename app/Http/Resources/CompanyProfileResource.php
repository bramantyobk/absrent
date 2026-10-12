<?php

namespace App\Http\Resources;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyProfile
 */
class CompanyProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'support_whatsapp' => $this->support_whatsapp,
            'operating_hours' => $this->operatingHours(),
            'bank_accounts' => BankAccountResource::collection($this->whenLoaded('bankAccounts')),
            'updated_at' => $this->updated_at,
        ];
    }
}
