@extends('layouts.app', [
    'title' => 'الرئيسية | إدارة مخزون الورد',
    'module' => 'flowers',
    'moduleName' => 'إدارة مخزون الورد',
])

@section('content')
    <section class="rounded-2xl border border-rose-200 bg-gradient-to-l from-rose-50 to-white p-6 sm:p-8">
        <p class="text-sm font-bold text-rose-800">إدارة مخزون الورد</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">مرحبًا بك في منطقة الورد</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">تم تجهيز مساحة التشغيل المستقلة. لا تتضمن هذه المنطقة سجلات متاجر أو عملاء؛ أي مستلم سيُسجل لاحقًا كنص اختياري في مستند الخروج أو الفاتورة.</p>
    </section>
@endsection
