<?php

namespace App\Models;

use Database\Factories\SalamiDeliveryAgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'notes', 'is_active', 'created_by', 'updated_by'])]
class SalamiDeliveryAgent extends Model
{
    /** @use HasFactory<SalamiDeliveryAgentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(SalamiCustomer::class, 'delivery_agent_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalamiInvoice::class, 'delivery_agent_id');
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
