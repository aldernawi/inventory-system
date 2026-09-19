@extends('layouts.app', ['title' => 'اختر النظام'])

@section('content')
    <section class="mx-auto max-w-5xl py-4 sm:py-10">
        <div class="mb-8 text-center sm:mb-12"><p class="text-sm font-medium text-slate-500">مرحبًا، {{ auth()->user()->name }}</p><h1 class="mt-2 text-3xl font-bold text-slate-950 sm:text-4xl">اختر النظام</h1><p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-slate-600 sm:text-base">اختر منطقة العمل التي تريد إدارتها. تبقى بيانات وإجراءات كل مخزن منفصلة تمامًا.</p></div>
        <div class="grid justify-center gap-6 md:grid-cols-2">
            <a href="{{ route('salami.dashboard') }}" class="group min-h-72 rounded-2xl border border-amber-200 bg-amber-50/70 p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-amber-300 hover:shadow-xl hover:shadow-amber-950/10 sm:p-8">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-400 text-xl font-bold text-amber-950">س</span><h2 class="mt-8 text-2xl font-bold text-slate-950">إدارة السلامي</h2><p class="mt-3 max-w-sm text-sm leading-7 text-slate-600">المخزون، الاستلامات، المحلات والفواتير ضمن مساحة عمل مستقلة.</p><span class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-amber-900">دخول إلى النظام <span class="transition group-hover:-translate-x-1">←</span></span>
            </a>
            <a href="{{ route('flowers.dashboard') }}" class="group min-h-72 rounded-2xl border border-rose-200 bg-rose-50/70 p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-rose-300 hover:shadow-xl hover:shadow-rose-950/10 sm:p-8">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-500 text-xl font-bold text-white">و</span><h2 class="mt-8 text-2xl font-bold text-slate-950">إدارة الورد</h2><p class="mt-3 max-w-sm text-sm leading-7 text-slate-600">المخزون، الاستلامات، التالف والفواتير ضمن مساحة عمل مستقلة.</p><span class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-rose-900">دخول إلى النظام <span class="transition group-hover:-translate-x-1">←</span></span>
            </a>
        </div>
        @can('reset-temporary-data')
            <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-right">
                <p class="text-sm font-bold text-rose-800">أداة مؤقتة</p>
                <div class="mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <p class="text-sm leading-6 text-rose-900">إزالة بيانات التشغيل وإبقاء حسابات المستخدمين فقط.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('system.flower-data-reset') }}" class="shrink-0 rounded-lg border border-rose-300 bg-white px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100">مسح بيانات الورد فقط</a>
                        <a href="{{ route('system.temporary-data-reset') }}" class="shrink-0 rounded-lg border border-rose-300 bg-white px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100">إزالة كل البيانات</a>
                    </div>
                </div>
            </div>
        @endcan
    </section>
@endsection
