<?php

namespace App\Models;

use App\Services\VehiclePhotoStorage;
use App\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['brand', 'model', 'plate_number', 'type', 'year', 'color', 'price_per_day', 'price_per_hour', 'photo', 'is_available'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'is_available' => 'boolean',
        ];
    }

    /**
     * URL foto, atau gambar placeholder jika kendaraan belum punya foto.
     *
     * @return Attribute<string, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->photo
            ? Storage::disk(VehiclePhotoStorage::DISK)->url($this->photo)
            : asset('images/vehicle-placeholder.png'));
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
