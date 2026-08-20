<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesSalamiAccess
{
    protected function authorizeSalamiMasterData(): void
    {
        Gate::authorize('manage-salami-master-data');
    }

    protected function authorizeSalamiOpeningStock(): void
    {
        Gate::authorize('register-salami-opening-stock');
    }

    protected function authorizeSalamiReceipts(): void
    {
        Gate::authorize('operate-salami-receipts');
    }
}
