<?php

namespace App\Models;

use App\Enums\SupplierScope;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'company_name', 'phone', 'country', 'module_scope', 'notes', 'is_active', 'created_by', 'updated_by'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'module_scope' => SupplierScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function salamiReceipts(): HasMany
    {
        return $this->hasMany(SalamiReceipt::class);
    }

    public function flowerReceipts(): HasMany
    {
        return $this->hasMany(FlowerReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
