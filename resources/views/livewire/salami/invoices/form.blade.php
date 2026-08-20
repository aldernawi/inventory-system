<section class="space-y-6">
    @php($preview = $this->preview())

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold text-amber-800">فواتير البيع</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $invoice ? 'تعديل مسودة فاتورة' : 'فاتورة سلامي جديدة' }}</h1>
            <p class="mt-1 text-sm text-slate-600">الأسعار والأرصدة المعروضة للمراجعة، أما الخصم الفعلي فيتم بعد القفل والتحقق داخل المعاملة.</p>
        </div>
        <a wire:navigate href="{{ route('salami.invoices.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-950">سجل الفواتير</a>
    </div>

    <form wire:submit="saveDraft" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-bold text-slate-950">بيانات الفاتورة</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block text-sm font-bold text-slate-700">المحل / العميل
                    <select wire:model="customerId" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                        <option value="">اختر المحل</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customerId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-bold text-slate-700">تاريخ الفاتورة
                    <input wire:model="invoiceDate" type="date" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @error('invoiceDate') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-bold text-slate-700">نوع الدفع
                    <select wire:model.live="paymentType" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                        <option value="cash">نقدي</option>
                        <option value="credit">آجل</option>
                        <option value="partial">جزئي</option>
                    </select>
                    @error('paymentType') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-bold text-slate-700">الخصم
                    <input wire:model.live.debounce.250ms="discountAmount" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @error('discountAmount') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-bold text-slate-700">المبلغ المدفوع
                    <input wire:model.live.debounce.250ms="paidAmount" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @error('paidAmount') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-bold text-slate-700 sm:col-span-2 lg:col-span-3">ملاحظات
                    <textarea wire:model="notes" rows="2" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500"></textarea>
                </label>
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div><h2 class="font-bold text-slate-950">بنود الفاتورة</h2><p class="mt-1 text-sm text-slate-600">لا يمكن تكرار الصنف في الفاتورة نفسها.</p></div>
                <button type="button" wire:click="addItem" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800 hover:bg-amber-100">إضافة صنف</button>
            </div>
            @error('items') <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p> @enderror

            @foreach ($items as $index => $item)
                @php($selectedProduct = isset($productLookup[(int) ($item['product_id'] ?: 0)]) ? $productLookup[(int) $item['product_id']] : null)
                @php($itemPreview = $preview['items'][$index] ?? ['line_total' => '0.000'])
                <article wire:key="invoice-item-{{ $index }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-3">
                        <p class="text-sm font-bold text-slate-700">البند {{ $index + 1 }}</p>
                        <button type="button" wire:click="removeItem({{ $index }})" class="text-xs font-bold text-rose-700 hover:text-rose-900">إزالة</button>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block text-sm font-bold text-slate-700 sm:col-span-2">الصنف
                            <select wire:model.live="items.{{ $index }}.product_id" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                                <option value="">اختر الصنف</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @disabled(! $product->is_active && (string) $product->id !== $item['product_id'])>{{ $product->name }} · {{ $product->code }}</option>
                                @endforeach
                            </select>
                            @error("items.$index.product_id") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 sm:col-span-2">
                            <p><span class="font-bold text-slate-700">الوحدة:</span> {{ $selectedProduct?->unit ?? '—' }}</p>
                            <p class="mt-1"><span class="font-bold text-slate-700">الرصيد الحالي:</span> {{ $selectedProduct?->current_quantity ?? '—' }}</p>
                        </div>
                        <label class="block text-sm font-bold text-slate-700">الكمية
                            <input wire:model.live.debounce.250ms="items.{{ $index }}.quantity" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                            @error("items.$index.quantity") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-sm font-bold text-slate-700">سعر الوحدة
                            <input wire:model.live.debounce.250ms="items.{{ $index }}.unit_price" inputmode="decimal" type="text" class="mt-2 w-full rounded-lg border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                            @error("items.$index.unit_price") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm">
                            <p class="text-xs font-bold text-amber-800">إجمالي البند</p>
                            <p class="mt-1 text-xl font-bold text-amber-950">{{ $itemPreview['line_total'] }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                            <p class="text-xs font-bold text-slate-500">الرصيد المتوقع بعد البيع</p>
                            <p class="mt-1 text-lg font-bold text-slate-950">{{ $selectedProduct && $preview['valid'] ? \App\Support\Quantity::from($selectedProduct->current_quantity)->minus(\App\Support\Quantity::from($item['quantity'] ?: '0'))->toString() : '—' }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:grid-cols-2 lg:grid-cols-5">
            <div><p class="text-xs font-bold text-amber-800">المجموع الفرعي</p><p class="mt-1 text-xl font-bold text-amber-950">{{ $preview['subtotal'] }}</p></div>
            <div><p class="text-xs font-bold text-amber-800">الخصم</p><p class="mt-1 text-xl font-bold text-amber-950">{{ $preview['discount'] }}</p></div>
            <div><p class="text-xs font-bold text-amber-800">الإجمالي</p><p class="mt-1 text-xl font-bold text-amber-950">{{ $preview['total'] }}</p></div>
            <div><p class="text-xs font-bold text-amber-800">المدفوع</p><p class="mt-1 text-xl font-bold text-amber-950">{{ $preview['paid'] }}</p></div>
            <div><p class="text-xs font-bold text-amber-800">المتبقي</p><p class="mt-1 text-xl font-bold text-amber-950">{{ $preview['remaining'] }}</p></div>
            @if (! $preview['valid'])<p class="text-sm font-bold text-rose-700 sm:col-span-2 lg:col-span-5">تحقق من القيم: الكمية موجبة، والسعر والخصم والمدفوع لا تقل عن صفر.</p>@endif
        </div>

        @error('invoice') <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div> @enderror
        <div class="sticky bottom-3 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
            <button wire:click="saveDraft" wire:loading.attr="disabled" type="button" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">حفظ كمسودة</button>
            <button wire:click="confirm" wire:loading.attr="disabled" type="button" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-amber-950 hover:bg-amber-400">اعتماد وخصم المخزون</button>
            <span wire:loading class="self-center text-xs font-bold text-amber-800">جارٍ الحفظ…</span>
        </div>
    </form>
</section>
