<section class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold text-amber-800">بيانات أساسية</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">أصناف السلامي</h1>
            <p class="mt-1 text-sm text-slate-600">الرصيد الحالي للعرض فقط؛ تعديله يتم عبر حركات مخزون مدققة.</p>
        </div>
        @can('manage-salami-master-data')
            <a wire:navigate href="{{ route('salami.products.create') }}" class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-amber-950 transition hover:bg-amber-400">إضافة صنف</a>
        @endcan
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-[1fr_11rem]">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="ابحث بالاسم أو الكود" class="rounded-lg border-slate-300 text-sm focus:border-amber-500 focus:ring-amber-500">
            <select wire:model.live="status" class="rounded-lg border-slate-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                <option value="all">كل الحالات</option>
                <option value="active">نشط</option>
                <option value="inactive">غير نشط</option>
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-right">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500">
                    <tr>
                        <th class="px-4 py-3">الصنف</th><th class="px-4 py-3">الكود</th><th class="px-4 py-3">الوحدة</th><th class="px-4 py-3">الرصيد الحالي</th><th class="px-4 py-3">الحد الأدنى</th><th class="px-4 py-3">الحالة</th><th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($products as $product)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-900"><a wire:navigate href="{{ route('salami.products.show', $product) }}" class="hover:text-amber-700">{{ $product->name }}</a></td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ $product->code }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $product->unit }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $product->current_quantity }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $product->minimum_quantity }}</td>
                            <td class="px-4 py-3"><span @class(['rounded-full px-2.5 py-1 text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $product->is_active, 'bg-slate-200 text-slate-700' => ! $product->is_active])>{{ $product->is_active ? 'نشط' : 'غير نشط' }}</span></td>
                            <td class="px-4 py-3">
                                @can('manage-salami-master-data')
                                    <div class="flex items-center gap-3 text-xs font-bold">
                                        <a wire:navigate href="{{ route('salami.products.edit', $product) }}" class="text-amber-800 hover:text-amber-950">تعديل</a>
                                        <button wire:click="toggleActive({{ $product->id }})" wire:confirm="هل تريد تغيير حالة هذا الصنف؟" class="text-slate-600 hover:text-slate-950">{{ $product->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">لا توجد أصناف مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $products->links() }}</div>
    </div>
</section>
