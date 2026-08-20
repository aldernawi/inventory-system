<x-layouts.guest title="تسجيل الدخول | نظام إدارة المخزون">
    <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 sm:p-8">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-950 text-xl font-bold text-white">م</div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950">نظام إدارة المخزون</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">سجّل الدخول للوصول إلى إدارة السلامي والورد.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr"
                    class="block w-full rounded-xl border-slate-300 px-3 py-2.5 text-right shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/15 @error('email') border-red-400 @enderror">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">كلمة المرور</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" dir="ltr"
                    class="block w-full rounded-xl border-slate-300 px-3 py-2.5 text-right shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/15 @error('password') border-red-400 @enderror">
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-slate-950 focus:ring-slate-950">
                تذكرني على هذا الجهاز
            </label>

            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2">
                تسجيل الدخول
            </button>
        </form>
    </section>
</x-layouts.guest>
