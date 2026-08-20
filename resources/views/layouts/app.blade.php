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
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            @isset($module)
                @include('components.navigation.module-sidebar', ['module' => $module])
            @endisset

            <div class="min-w-0">
                <header class="border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $moduleName ?? 'نظام إدارة المخزون' }}</p>
                            <p class="mt-1 text-xs text-slate-500">إدارة يومية واضحة ودقيقة للمخزون</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('dashboard') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 sm:block">
                                تبديل النظام
                            </a>
                            <div class="hidden text-left sm:block">
                                <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                    تسجيل الخروج
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="mx-auto max-w-7xl p-4 sm:p-6">
                    @if (session('status'))
                        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
