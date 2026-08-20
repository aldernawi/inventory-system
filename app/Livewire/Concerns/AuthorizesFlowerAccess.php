<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesFlowerAccess
{
    protected function authorizeFlowerMasterData(): void
    {
        Gate::authorize('manage-flower-master-data');
    }

    protected function authorizeFlowerOpeningStock(): void
    {
        Gate::authorize('register-flower-opening-stock');
    }

    protected function authorizeFlowerReceipts(): void
    {
        Gate::authorize('operate-flower-receipts');
    }

    protected function authorizeFlowerWaste(): void
    {
        Gate::authorize('register-flower-waste');
    }

    protected function authorizeFlowerExits(): void
    {
        Gate::authorize('operate-flower-exits');
    }

    protected function authorizeFlowerInventory(): void
    {
        Gate::authorize('view-flower-inventory');
    }
}
