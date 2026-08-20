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

    protected function authorizeSalamiInvoices(): void
    {
        Gate::authorize('operate-salami-invoices');
    }

    protected function authorizeSalamiInvoiceCancellation(): void
    {
        Gate::authorize('cancel-salami-invoices');
    }

    protected function authorizeSalamiWaste(): void
    {
        Gate::authorize('register-salami-waste');
    }

    protected function authorizeSalamiAdjustments(): void
    {
        Gate::authorize('perform-salami-adjustments');
    }

    protected function authorizeSalamiReports(): void
    {
        Gate::authorize('view-salami-reports');
    }
}
