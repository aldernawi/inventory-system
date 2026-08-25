<section class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-bold text-amber-800">إضافة مخزون</p><h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $receipt ? 'تعديل مسودة استلام' : 'استلام بضاعة جديدة' }}</h1><p class="mt-1 text-sm text-slate-600">الأرقام المعروضة تقديرية؛ الرصيد الحقيقي يعاد احتسابه داخل المعاملة عند الاعتماد.</p></div>
        <a wire:navigate href="{{ route('salami.receipts.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-950">سجل الاستلامات</a>
    </div>

    <form wire:submit="saveDraft" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-bold text-slate-950">بيانات الإيصال</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-bold text-slate-700">المورد<select wire:model="supplierId" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"><option value="">اختر المورد</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->company_name ? ' — '.$supplier->company_name : '' }}</option>@endforeach</select>@error('supplierId')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold text-slate-700">تاريخ الاستلام<input wire:model="receiptDate" type="date" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">@error('receiptDate')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold text-slate-700 sm:col-span-2">رقم فاتورة المورد (اختياري)<input wire:model="supplierInvoiceNumber" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></label>
                <label class="block text-sm font-bold text-slate-700 sm:col-span-2">ملاحظات<textarea wire:model="notes" rows="2" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></textarea></label>
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between"><div><h2 class="font-bold text-slate-950">بنود الاستلام</h2><p class="mt-1 text-sm text-slate-600">اختر قطعة أو صندوق وأدخل عدد القطع الفعلي داخل الصندوق عند الحاجة.</p></div><button type="button" wire:click="addItem" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800 hover:bg-amber-100">إضافة صنف</button></div>
            @error('items')<p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            @foreach($items as $index => $item)
                @php($preview = $this->preview($item))
                @php($selectedProduct = isset($productLookup[(int) ($item['product_id'] ?: 0)]) ? $productLookup[(int) $item['product_id']] : null)
                <article wire:key="receipt-item-{{ $index }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-3"><p class="text-sm font-bold text-slate-700">البند {{ $index + 1 }}</p><button type="button" wire:click="removeItem({{ $index }})" class="text-xs font-bold text-rose-700 hover:text-rose-900">إزالة</button></div>
                    <div class="space-y-5 p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-bold text-slate-700">الصنف<select wire:model.live="items.{{ $index }}.product_id" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"><option value="">اختر الصنف</option>@foreach($products as $product)<option value="{{ $product->id }}" @disabled(! $product->is_active && (string) $product->id !== $item['product_id'])>{{ $product->name }} · {{ $product->code }}</option>@endforeach</select>@error("items.$index.product_id")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600"><p><span class="font-bold text-slate-700">وحدة رصيد الصنف:</span> {{ $selectedProduct?->unit ?? '—' }}</p><p class="mt-1"><span class="font-bold text-slate-700">الرصيد الحالي:</span> {{ $selectedProduct?->current_quantity ?? '—' }}</p></div>
                            <label class="block text-sm font-bold text-slate-700">وحدة الاستلام<select wire:model.live="items.{{ $index }}.unit_type" class="mt-2 w-full rounded-lg border-slate-300"><option value="piece">قطعة</option><option value="box">صندوق</option></select>@error("items.$index.unit_type")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                            @if(($item['unit_type'] ?? 'piece') === 'box')<label class="block text-sm font-bold text-slate-700">قطع داخل الصندوق<input wire:model.live.debounce.250ms="items.{{ $index }}.pieces_per_box" inputmode="numeric" type="text" class="mt-2 w-full rounded-lg border-slate-300">@error("items.$index.pieces_per_box")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>@endif
                            <label class="block text-sm font-bold text-slate-700">المتوقع<input wire:model.live.debounce.250ms="items.{{ $index }}.expected_quantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300">@error("items.$index.expected_quantity")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                            <label class="block text-sm font-bold text-slate-700">الواصل<input wire:model.live.debounce.250ms="items.{{ $index }}.received_quantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300">@error("items.$index.received_quantity")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                            <label class="block text-sm font-bold text-slate-700">التالف عند الوصول<input wire:model.live.debounce.250ms="items.{{ $index }}.damaged_quantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300">@error("items.$index.damaged_quantity")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                            <label class="block text-sm font-bold text-slate-700">سعر الشراء<input wire:model="items.{{ $index }}.purchase_price" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300">@error("items.$index.purchase_price")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                        </div>
                        <label class="block text-sm font-bold text-slate-700">ملاحظات البند<textarea wire:model="items.{{ $index }}.notes" rows="2" class="mt-2 w-full rounded-lg border-slate-300"></textarea></label>
                        <div class="grid gap-3 rounded-xl border border-amber-100 bg-amber-50 p-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div><p class="text-xs font-bold text-amber-800">النقص</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $preview['shortage_quantity'] }}</p></div>
                            <div><p class="text-xs font-bold text-amber-800">الزيادة</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $preview['surplus_quantity'] }}</p></div>
                            <div><p class="text-xs font-bold text-amber-800">المضاف للمخزون</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $preview['accepted_stock_quantity'] }} {{ $selectedProduct?->unit ?? 'قطعة' }}</p></div>
                            <div><p class="text-xs font-bold text-amber-800">الرصيد الحالي</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $selectedProduct?->current_quantity ?? '—' }}</p></div>
                            <div><p class="text-xs font-bold text-amber-800">الرصيد المتوقع</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $selectedProduct && $preview['valid'] ? \App\Support\Quantity::from($selectedProduct->current_quantity)->plus(\App\Support\Quantity::from($preview['accepted_stock_quantity']))->toString() : '—' }}</p></div>
                        </div>
                        @if(! $preview['valid'])<p class="text-xs font-bold text-rose-700">تحقق من أن التالف لا يزيد على الواصل وأن القيم موجبة أو صفر.</p>@endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="sticky bottom-3 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
            <button wire:click.prevent="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" type="button" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">حفظ كمسودة</button>
            <button wire:click.prevent="confirm" wire:loading.attr="disabled" wire:target="confirm" type="button" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">اعتماد وإضافة للمخزون</button>
            <span wire:loading class="self-center text-xs font-bold text-amber-800">جارٍ الحفظ…</span>
        </div>
    </form>
</section>
