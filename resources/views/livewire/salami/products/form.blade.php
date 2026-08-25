<section class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-sm font-bold text-amber-800">أصناف السلامي</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $product ? 'تعديل الصنف' : 'إضافة صنف جديد' }}</h1>
        <p class="mt-1 text-sm text-slate-600">يبدأ كل صنف برصيد صفري. لا يمكن إدخال الرصيد من هذه الشاشة.</p>
    </div>
    <form wire:submit="save" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-bold text-slate-700">اسم الصنف<input wire:model="name" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold text-slate-700">الكود<input wire:model="code" type="text" class="mt-2 w-full rounded-lg border-slate-300 font-mono focus:border-amber-500 focus:ring-amber-500">@error('code')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold text-slate-700">وحدة رصيد الصنف<input wire:model="unit" type="text" placeholder="قطعة" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('unit')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror<p class="mt-1 text-xs font-normal text-slate-500">لصنف يُباع بالصندوق والقطعة، اجعل وحدة الرصيد «قطعة».</p></label>
            <label class="block text-sm font-bold text-slate-700">الحد الأدنى<input wire:model="minimumQuantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('minimumQuantity')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold text-slate-700">سعر الشراء<input wire:model="purchasePrice" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('purchasePrice')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold text-slate-700">سعر البيع<input wire:model="salePrice" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('salePrice')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
        </div>
        @if ($product)
            <div class="rounded-xl bg-slate-50 p-4 text-sm"><span class="font-bold text-slate-700">الرصيد الحالي:</span> <span class="font-bold text-slate-950">{{ $product->current_quantity }}</span> <span class="text-slate-500">({{ $product->unit }})</span></div>
        @endif
        <label class="block text-sm font-bold text-slate-700">ملاحظات<textarea wire:model="notes" rows="3" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></textarea>@error('notes')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
        <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700"><input wire:model="isActive" type="checkbox" class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">الصنف نشط</label>
        <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5"><button wire:loading.attr="disabled" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">حفظ</button><a wire:navigate href="{{ route('salami.products.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100">إلغاء</a></div>
    </form>
</section>
