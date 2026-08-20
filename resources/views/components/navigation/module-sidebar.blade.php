@php
    $isSalami = $module === 'salami';
    $moduleName = $isSalami ? 'إدارة مخزن السلامي' : 'إدارة مخزون الورد';
    $dashboardRoute = $isSalami ? 'salami.dashboard' : 'flowers.dashboard';
    $items = $isSalami
        ? [
            ['label' => 'الأصناف', 'route' => 'salami.products.index'],
            ['label' => 'إضافة مخزون', 'route' => 'salami.receipts.create'],
            ['label' => 'سجل الاستلامات', 'route' => 'salami.receipts.index'],
            ['label' => 'المخزون الحالي', 'route' => 'salami.inventory.index'],
            ['label' => 'حركة الأصناف', 'route' => 'salami.inventory.index'],
            ['label' => 'التالف', 'route' => 'salami.waste.index'],
            ['label' => 'تسوية المخزون', 'route' => 'salami.adjustments.index'],
            ['label' => 'الموردون', 'route' => 'salami.suppliers.index'],
            ['label' => 'المحلات', 'route' => 'salami.customers.index'],
            ['label' => 'فاتورة جديدة', 'route' => 'salami.invoices.create'],
            ['label' => 'سجل الفواتير', 'route' => 'salami.invoices.index'],
            ['label' => 'التقارير', 'route' => 'salami.reports.index'],
        ]
        : [
            ['label' => 'أنواع الورد', 'route' => 'flowers.products.index'],
            ['label' => 'إضافة مخزون', 'route' => 'flowers.receipts.create'],
            ['label' => 'سجل الاستلامات', 'route' => 'flowers.receipts.index'],
            ['label' => 'المخزون الحالي', 'route' => 'flowers.inventory.index'],
            ['label' => 'حركة الورد', 'route' => 'flowers.inventory.index'],
            ['label' => 'التالف', 'route' => 'flowers.waste.index'],
            ['label' => 'تسوية المخزون'],
            ['label' => 'خروج الورد', 'route' => 'flowers.exits.index'],
            ['label' => 'الموردون', 'route' => 'flowers.suppliers.index'],
            ['label' => 'فاتورة جديدة', 'route' => 'flowers.invoices.create'],
            ['label' => 'سجل الفواتير', 'route' => 'flowers.invoices.index'],
            ['label' => 'التقارير', 'route' => 'flowers.reports.index'],
        ];
@endphp

<aside class="border-b border-slate-200 bg-slate-950 text-slate-100 lg:min-h-screen lg:border-b-0 lg:border-l">
    <div class="border-b border-slate-800 px-5 py-5">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $isSalami ? 'bg-amber-400 text-amber-950' : 'bg-rose-400 text-rose-950' }} text-lg font-bold">
                {{ $isSalami ? 'س' : 'و' }}
            </span>
            <span>
                <span class="block text-sm font-bold">{{ $moduleName }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">منطقة التشغيل</span>
            </span>
        </a>
    </div>

    <nav class="grid gap-1 p-3 sm:grid-cols-2 lg:block" aria-label="التنقل الرئيسي">
        <a href="{{ route($dashboardRoute) }}" @class([
            'rounded-lg px-3 py-2.5 text-sm font-semibold transition',
            'bg-white/10 text-white' => request()->routeIs($dashboardRoute),
            'text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs($dashboardRoute),
        ])>
            الرئيسية
        </a>

        @foreach ($items as $item)
            @if (isset($item['route']))
                <a wire:navigate href="{{ route($item['route']) }}" @class([
                    'rounded-lg px-3 py-2.5 text-sm font-semibold transition',
                    'bg-white/10 text-white' => request()->routeIs($item['route'].'*'),
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs($item['route'].'*'),
                ])>
                    {{ $item['label'] }}
                </a>
            @else
                <span class="cursor-not-allowed rounded-lg px-3 py-2.5 text-sm text-slate-500" aria-disabled="true" title="سيتم توفير هذه الشاشة في مرحلة لاحقة">
                    {{ $item['label'] }}
                </span>
            @endif
        @endforeach
    </nav>
</aside>
