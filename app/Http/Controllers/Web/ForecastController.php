<?php

namespace App\Http\Controllers\Web;

use App\Enums\ForecastSource;
use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Services\ForecastPresentationService;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function __construct(private readonly ForecastPresentationService $presentation) {}

    public function cloud(): View
    {
        return $this->render(ForecastSource::Cloud);
    }

    public function network(): View
    {
        return $this->render(ForecastSource::Network);
    }

    public function show(Forecast $forecast): View
    {
        $this->authorize('view', $forecast);

        $forecast->load('predictions');

        return view('forecasts.show', [
            'forecast' => $forecast,
            'source' => $forecast->source_type,
            'series' => $this->presentation->series($forecast),
            'warnings' => $this->presentation->warnings($forecast),
        ]);
    }

    private function render(ForecastSource $source): View
    {
        $this->authorize('viewAny', Forecast::class);

        $forecast = Forecast::latestActiveFor($source);

        return view('forecasts.index', [
            'source' => $source,
            'forecast' => $forecast,
            'series' => $forecast ? $this->presentation->series($forecast) : [],
            'warnings' => $forecast ? $this->presentation->warnings($forecast) : collect(),
            'history' => Forecast::query()
                ->forSource($source)
                ->withCount([
                    'predictions',
                    'predictions as warning_predictions_count' => fn ($query) => $query->whereIn('risk_level', ['HIGH', 'CRITICAL']),
                ])
                ->latest('generated_at')
                ->paginate(8),
            'metrics' => config('forecast.metrics.'.$source->metricConfigKey(), []),
        ]);
    }
}
