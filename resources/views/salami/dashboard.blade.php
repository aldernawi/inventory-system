@extends('layouts.app', [
    'title' => 'الرئيسية | إدارة مخزن السلامي',
    'module' => 'salami',
    'moduleName' => 'إدارة مخزن السلامي',
])

@section('content')
    <section class="rounded-2xl border border-amber-200 bg-gradient-to-l from-amber-50 to-white p-6 sm:p-8">
        <p class="text-sm font-bold text-amber-800">إدارة مخزن السلامي</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">مرحبًا بك في منطقة السلامي</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">تم تجهيز مساحة التشغيل المستقلة. ستظهر مؤشرات المخزون والإجراءات اليومية هنا عند اكتمال مراحل المخزون التالية.</p>
    </section>
@endsection
