<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'نظام إدارة المخزون' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen font-sans text-slate-900 antialiased">
        @isset($module)
            <div data-app-shell class="app-shell app-shell--{{ $module }} min-h-screen lg:flex">
                <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-slate-950/45 backdrop-blur-[1px] lg:hidden"></div>
                @include('components.navigation.module-sidebar', ['module' => $module])
                <div class="min-w-0">
                    <header class="sticky top-0 z-20 border-b border-slate-200/90 bg-white/95 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
                        <div class="mx-auto flex max-w-[1500px] items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <button type="button" data-sidebar-open class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-700 lg:hidden" aria-label="فتح القائمة"><span class="text-xl leading-none">☰</span></button>
                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $moduleName ?? 'نظام إدارة المخزون' }}</p><p class="hidden truncate text-xs text-slate-500 sm:block">إدارة يومية واضحة ودقيقة للمخزون</p></div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                                <a href="{{ route('dashboard') }}" class="app-button app-button-secondary hidden sm:inline-flex">تبديل النظام</a>
                                <div class="hidden text-left md:block"><p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p><p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p></div>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="app-button border border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100">تسجيل الخروج</button></form>
                            </div>
                        </div>
                    </header>
                    <main class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
                        @if (session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
                        @yield('content')
                    </main>
                </div>
            </div>
        @else
            <div class="min-h-screen">
                <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-8"><div class="mx-auto flex max-w-[1500px] items-center justify-between gap-4"><a href="{{ route('dashboard') }}" class="font-semibold text-slate-900">نظام إدارة المخزون</a><div class="flex items-center gap-3"><div class="hidden text-left sm:block"><p class="text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p></div><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="app-button border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">تسجيل الخروج</button></form></div></div></header>
                <main class="mx-auto max-w-[1500px] px-4 py-8 sm:px-8 sm:py-12">@yield('content')</main>
            </div>
        @endisset

        @livewireScripts
    </body>
</html>
