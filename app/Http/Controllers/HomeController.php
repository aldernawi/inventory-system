<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function home(): RedirectResponse
    {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    }

    public function dashboard(): View
    {
        return view('dashboard');
    }
}
