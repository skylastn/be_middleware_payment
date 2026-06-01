<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\System\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function index(): View
    {
        return view('dashboard.index', $this->dashboardService->monitoringData());
    }
}
