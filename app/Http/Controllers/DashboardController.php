<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Placeholder start page. The KPIs and the planning calendar
 * (US-25, US-26) are added in build step 8.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
