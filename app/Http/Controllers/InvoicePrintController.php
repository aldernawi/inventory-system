<?php

namespace App\Http\Controllers;

use App\Models\FlowerInvoice;
use App\Models\SalamiInvoice;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function salami(SalamiInvoice $invoice): View
    {
        return view('salami.invoices.print', [
            'invoice' => $invoice->load(['customer', 'items.product', 'createdBy', 'confirmedBy', 'cancelledBy']),
        ]);
    }

    public function flowers(FlowerInvoice $invoice): View
    {
        return view('flowers.invoices.print', [
            'invoice' => $invoice->load(['items.product', 'createdBy', 'confirmedBy', 'cancelledBy']),
        ]);
    }
}
