@extends('layouts.app', ['title' => 'إزالة البيانات المؤقتة'])

@section('content')
    <section class="mx-auto max-w-2xl py-4 sm:py-10">
        <div class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm">
            <div class="border-b border-rose-200 bg-rose-50 px-5 py-5 sm:px-7">
                <p class="text-sm font-bold text-rose-700">أداة مؤقتة للأدمن فقط</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">إزالة البيانات المؤقتة</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">هذا الإجراء لا يمكن التراجع عنه من داخل النظام.</p>
            </div>

            <div class="space-y-5 px-5 py-6 sm:px-7">
                <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 text-sm leading-7 text-rose-950">
                    <p class="font-bold">سيتم حذف:</p>
                    <p class="mt-1">الأصناف، الموردون، المناديب، المحلات، الاستلامات، الفواتير، الدفعات، حركات المخزون، التالف، التسويات وخروج الورد.</p>
                    <p class="mt-2 font-bold">لن يتم حذف حسابات المستخدمين أو صلاحياتهم.</p>
                </div>

                <form method="POST" action="{{ route('system.temporary-data-reset.destroy') }}" class="space-y-4">
                    @csrf
                    @method('DELETE')

                    <label class="block text-sm font-bold text-slate-700">
                        للتأكيد اكتب: <span dir="ltr" class="font-mono">مسح البيانات</span>
                        <input name="confirmation" value="{{ old('confirmation') }}" autocomplete="off" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
                        @error('confirmation')
                            <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                        <button type="submit" class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">إزالة كل البيانات المؤقتة</button>
                        <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
