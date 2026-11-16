<?php

namespace App\Http\Controllers\Web;

use App\Enums\ForecastSource;
use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetricsService $metrics): View
    {
        $cloud = $metrics->latestForecast(ForecastSource::Cloud);
        $network = $metrics->latestForecast(ForecastSource::Network);

        return view('dashboard', [
            'summary' => $metrics->summary(),
            'trend' => $metrics->trend(14),
            'defectDistribution' => $metrics->defectDistribution(8),
            'recentInspections' => $metrics->recentInspections(8),
            'deviceStats' => $metrics->deviceStats(),
            'devices' => $metrics->deviceOverview(6),
            'cloudForecast' => $cloud,
            'networkForecast' => $network,
            'warnings' => $metrics->warnings(6),
            'warningCount' => $metrics->totalWarnings(),
        ]);
    }
}
