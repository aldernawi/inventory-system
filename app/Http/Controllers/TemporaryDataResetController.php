<?php

namespace App\Http\Controllers;

use App\Services\TemporaryDataResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemporaryDataResetController extends Controller
{
    public function index(): View
    {
        return view('system.temporary-data-reset');
    }

    public function destroy(Request $request, TemporaryDataResetService $temporaryDataReset): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:مسح البيانات'],
        ], [
            'confirmation.in' => 'اكتب عبارة «مسح البيانات» كما هي للتأكيد.',
        ]);

        $temporaryDataReset->clearBusinessData();

        return to_route('dashboard')->with('status', 'تمت إزالة كل بيانات التشغيل المؤقتة. حسابات المستخدمين بقيت كما هي.');
    }
}
