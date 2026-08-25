<?php

namespace App\Models;

use Database\Factories\SalamiCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['delivery_agent_id', 'name', 'contact_person', 'phone', 'area', 'address', 'notes', 'is_active', 'created_by', 'updated_by'])]
class SalamiCustomer extends Model
{
    /** @use HasFactory<SalamiCustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalamiInvoice::class, 'customer_id');
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(SalamiDeliveryAgent::class, 'delivery_agent_id');
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
