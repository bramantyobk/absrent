<?php

namespace App\Http\Resources;

use App\Models\RentalSetting;
use App\Services\RentalSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RentalSetting
 */
class RentalSettingsResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = app(RentalSettings::class);

        return [
            'values' => $settings->current(),
            'defaults' => $settings->defaults(),
            'limits' => array_map(fn (array $field): array => [
                'label' => $field['label'],
                'unit' => $field['unit'],
                'min' => $field['min'],
                'max' => $field['max'],
            ], RentalSettings::FIELDS),
            'updated_at' => $this->updated_at,
        ];
    }
}
