<?php

namespace App\Models;

use App\VehicleType;
use Database\Factories\EquipmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'quantity', 'is_active', 'sort_order'])]
class EquipmentItem extends Model
{
    /** @use HasFactory<EquipmentItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => VehicleType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<EquipmentItem>  $query
     * @param  array{search?: ?string, category?: ?string, status?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'));
    }

    /**
     * @param  Builder<EquipmentItem>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<EquipmentItem>  $query
     */
    #[Scope]
    protected function forCategory(Builder $query, VehicleType $category): void
    {
        $query->where('category', $category);
    }

    /**
     * @param  Builder<EquipmentItem>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }
}
