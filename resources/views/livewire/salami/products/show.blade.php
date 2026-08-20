<section class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-bold text-amber-800">صنف سلامي</p><h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $product->name }}</h1><p class="mt-1 text-sm text-slate-600">{{ $product->code }} · {{ $product->unit }}</p></div>
        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('salami.inventory.movements', $product) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">حركة الصنف</a>
            @can('manage-salami-master-data')<a wire:navigate href="{{ route('salami.products.edit', $product) }}" class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">تعديل</a>@endcan
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-sm font-bold text-amber-800">الرصيد الحالي</p><p class="mt-2 text-3xl font-bold text-amber-950">{{ $product->current_quantity }}</p><p class="mt-1 text-sm text-amber-800">{{ $product->unit }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-bold text-slate-500">الحد الأدنى</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $product->minimum_quantity }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-bold text-slate-500">سعر الشراء</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $product->purchase_price ?? '—' }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-bold text-slate-500">سعر البيع</p><p class="mt-2 text-2xl font-bold text-slate-950">{{ $product->sale_price ?? '—' }}</p></div>
    </div>
    @can('register-salami-opening-stock')
        @if ($product->current_quantity === '0.000' && $product->stockMovements()->doesntExist())
            <div class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-950">الرصيد الافتتاحي</h2><p class="mt-1 text-sm text-slate-600">يمكن تسجيله مرة واحدة فقط قبل وجود أي حركة مخزون.</p><a wire:navigate href="{{ route('salami.products.opening', $product) }}" class="mt-4 inline-flex rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">تسجيل الرصيد الافتتاحي</a></div>
        @endif
    @endcan
</section>
