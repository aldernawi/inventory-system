<section class="mx-auto max-w-2xl space-y-6">
    <div><p class="text-sm font-bold text-amber-800">حركة مخزون مدققة</p><h1 class="mt-1 text-2xl font-bold text-slate-950">تسجيل رصيد افتتاحي</h1></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="font-bold text-slate-950">{{ $product->name }}</p><p class="mt-1 text-sm text-slate-600">{{ $product->code }} · {{ $product->unit }}</p><p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm"><span class="font-bold">الرصيد الحالي:</span> {{ $product->current_quantity }}</p></div>
    <form wire:submit="register" class="space-y-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <label class="block text-sm font-bold text-slate-700">الكمية الافتتاحية<input wire:model="quantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-amber-300 bg-white focus:border-amber-500 focus:ring-amber-500">@error('quantity')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
        <label class="block text-sm font-bold text-slate-700">ملاحظات<textarea wire:model="notes" rows="3" class="mt-2 w-full rounded-lg border-amber-300 bg-white focus:border-amber-500 focus:ring-amber-500"></textarea></label>
        <p class="text-xs leading-5 text-amber-800">لا يمكن تكرار الرصيد الافتتاحي أو إدخاله بعد ظهور أي حركة مخزون.</p>
        <div class="flex gap-3"><button wire:loading.attr="disabled" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">تأكيد الرصيد الافتتاحي</button><a wire:navigate href="{{ route('salami.products.show', $product) }}" class="rounded-lg px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-white">إلغاء</a></div>
    </form>
</section>
