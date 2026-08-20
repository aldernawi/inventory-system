@extends('layouts.app', ['title' => 'اختر النظام'])

@section('content')
    <section class="mx-auto max-w-4xl py-6 sm:py-12">
        <div class="mb-8 text-center sm:mb-10">
            <p class="text-sm font-bold text-slate-500">مرحبًا، {{ auth()->user()->name }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">اختر النظام</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600 sm:text-base">اختر منطقة العمل المناسبة. تبقى بيانات وإجراءات كل مخزن منفصلة تمامًا.</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <a href="{{ route('salami.dashboard') }}" class="group rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-950/10 sm:p-8">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-400 text-xl font-bold text-amber-950">س</span>
                <h2 class="mt-6 text-xl font-bold text-slate-950">إدارة مخزن السلامي</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">الأصناف، الموردون، المحلات، الاستلامات، والفواتير.</p>
                <span class="mt-6 inline-flex text-sm font-bold text-amber-800 transition group-hover:translate-x-1">الدخول إلى النظام ←</span>
            </a>

            <a href="{{ route('flowers.dashboard') }}" class="group rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-rose-950/10 sm:p-8">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-400 text-xl font-bold text-rose-950">و</span>
                <h2 class="mt-6 text-xl font-bold text-slate-950">إدارة مخزون الورد</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">أنواع الورد، الموردون، الاستلامات، الخروج، والفواتير.</p>
                <span class="mt-6 inline-flex text-sm font-bold text-rose-800 transition group-hover:translate-x-1">الدخول إلى النظام ←</span>
            </a>
        </div>
    </section>
@endsection
