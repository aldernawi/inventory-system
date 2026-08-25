<section class="mx-auto max-w-3xl space-y-6">
    <div><p class="text-sm font-bold text-amber-800">السلامي فقط</p><h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $deliveryAgent ? 'تعديل مندوب' : 'إضافة مندوب توصيل' }}</h1><p class="mt-1 text-sm text-slate-600">المندوب ليس مستخدمًا للنظام؛ يُربط بالمحلات والفواتير لغرض المتابعة والتقارير.</p></div>
    <form wire:submit="save" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-5 sm:grid-cols-2"><label class="block text-sm font-bold text-slate-700">اسم المندوب<input wire:model="name" class="mt-2 w-full rounded-lg border-slate-300">@error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label><label class="block text-sm font-bold text-slate-700">الهاتف (اختياري)<input wire:model="phone" dir="ltr" class="mt-2 w-full rounded-lg border-slate-300">@error('phone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label></div>
        <label class="block text-sm font-bold text-slate-700">ملاحظات<textarea wire:model="notes" rows="3" class="mt-2 w-full rounded-lg border-slate-300"></textarea></label>
        <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700"><input wire:model="isActive" type="checkbox" class="rounded border-slate-300 text-amber-500">المندوب نشط</label>
        <div class="flex gap-3 border-t border-slate-100 pt-5"><button wire:loading.attr="disabled" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-bold text-amber-950">حفظ</button><a wire:navigate href="{{ route('salami.delivery-agents.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-bold text-slate-600">إلغاء</a></div>
    </form>
</section>
