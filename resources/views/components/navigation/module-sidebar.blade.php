@php
    $isSalami = $module === 'salami';
    $moduleName = $isSalami ? 'إدارة مخزن السلامي' : 'إدارة مخزون الورد';
    $dashboardRoute = $isSalami ? 'salami.dashboard' : 'flowers.dashboard';
    $groups = $isSalami ? [
        ['label' => null, 'items' => [['label' => 'الرئيسية', 'route' => 'salami.dashboard']]],
        ['label' => 'إدارة المخزون', 'items' => [['label' => 'الأصناف', 'route' => 'salami.products.index'], ['label' => 'إضافة مخزون', 'route' => 'salami.receipts.create'], ['label' => 'سجل الاستلامات', 'route' => 'salami.receipts.index'], ['label' => 'المخزون الحالي', 'route' => 'salami.inventory.index'], ['label' => 'حركة الأصناف', 'route' => 'salami.inventory.index'], ['label' => 'التالف', 'route' => 'salami.waste.index'], ['label' => 'تسوية المخزون', 'route' => 'salami.adjustments.index']]],
        ['label' => 'المبيعات', 'items' => [['label' => 'فاتورة جديدة', 'route' => 'salami.invoices.create'], ['label' => 'سجل الفواتير', 'route' => 'salami.invoices.index']]],
        ['label' => 'الإدارة', 'items' => [['label' => 'الموردون', 'route' => 'salami.suppliers.index'], ['label' => 'المندوبون', 'route' => 'salami.delivery-agents.index'], ['label' => 'المحلات', 'route' => 'salami.customers.index']]],
        ['label' => null, 'items' => [['label' => 'التقارير', 'route' => 'salami.reports.index']]],
    ] : [
        ['label' => null, 'items' => [['label' => 'الرئيسية', 'route' => 'flowers.dashboard']]],
        ['label' => 'إدارة المخزون', 'items' => [['label' => 'أنواع الورد', 'route' => 'flowers.products.index'], ['label' => 'إضافة مخزون', 'route' => 'flowers.receipts.create'], ['label' => 'سجل الاستلامات', 'route' => 'flowers.receipts.index'], ['label' => 'المخزون الحالي', 'route' => 'flowers.inventory.index'], ['label' => 'حركة الورد', 'route' => 'flowers.inventory.index'], ['label' => 'التالف', 'route' => 'flowers.waste.index'], ['label' => 'خروج الورد غير البيعي', 'route' => 'flowers.exits.index']]],
        ['label' => 'المبيعات', 'items' => [['label' => 'فاتورة جديدة', 'route' => 'flowers.invoices.create'], ['label' => 'سجل الفواتير', 'route' => 'flowers.invoices.index']]],
        ['label' => 'الإدارة', 'items' => [['label' => 'الموردون', 'route' => 'flowers.suppliers.index']]],
        ['label' => null, 'items' => [['label' => 'التقارير', 'route' => 'flowers.reports.index']]],
    ];
@endphp

<aside class="fixed inset-y-0 right-0 z-40 flex w-[17rem] translate-x-full flex-col border-l border-slate-800 bg-slate-950 text-slate-100 shadow-2xl transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none" data-sidebar>
    <div class="flex items-center justify-between border-b border-slate-800 px-5 py-5">
        <a href="{{ route($dashboardRoute) }}" class="flex min-w-0 items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isSalami ? 'bg-amber-400 text-amber-950' : 'bg-rose-400 text-rose-950' }} text-lg font-bold">{{ $isSalami ? 'س' : 'و' }}</span>
            <span class="min-w-0"><span class="block truncate text-sm font-bold">{{ $moduleName }}</span><span class="mt-0.5 block text-xs text-slate-400">منطقة التشغيل</span></span>
        </a>
        <button type="button" data-sidebar-close class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="إغلاق القائمة">×</button>
    </div>
    <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto p-3" aria-label="التنقل الرئيسي">
        @foreach ($groups as $group)
            <div>
                @if ($group['label'])<p class="px-3 pb-1.5 text-[11px] font-semibold text-slate-500">{{ $group['label'] }}</p>@endif
                <div class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <a data-sidebar-link wire:navigate href="{{ route($item['route']) }}" @class(['block rounded-lg px-3 py-2.5 text-sm font-medium transition', 'bg-white/12 text-white shadow-sm' => request()->routeIs($item['route'].'*'), 'text-slate-300 hover:bg-white/6 hover:text-white' => ! request()->routeIs($item['route'].'*')])>{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>

<style>.sidebar-open [data-sidebar] { transform: translateX(0); } .sidebar-open [data-sidebar-overlay] { display: block; }</style>
