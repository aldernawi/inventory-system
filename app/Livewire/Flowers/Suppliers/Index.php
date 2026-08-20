<?php

namespace App\Livewire\Flowers\Suppliers;

use App\Enums\SupplierScope;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $supplierId): void
    {
        $this->authorizeFlowerMasterData();
        $supplier = Supplier::query()->findOrFail($supplierId);

        if (! in_array($supplier->module_scope, [SupplierScope::Flower, SupplierScope::Both], true)) {
            abort(404);
        }

        $supplier->update(['is_active' => ! $supplier->is_active, 'updated_by' => auth()->id()]);
        session()->flash('status', 'تم تحديث حالة المورد.');
    }

    public function render(): View
    {
        $suppliers = Supplier::query()
            ->whereIn('module_scope', [SupplierScope::Flower->value, SupplierScope::Both->value])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.flowers.suppliers.index', compact('suppliers'));
    }
}
