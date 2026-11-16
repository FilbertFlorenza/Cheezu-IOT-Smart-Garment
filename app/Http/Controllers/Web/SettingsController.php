<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Forecast;
use App\Models\Inspection;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    public function index(ImageStorageService $images): View
    {
        $this->authorize('viewAny', User::class);

        $serviceTokens = PersonalAccessToken::query()
            ->with('tokenable')
            ->latest()
            ->get()
            ->filter(fn (PersonalAccessToken $token) => $token->tokenable?->isService())
            ->values();

        return view('settings.index', [
            'serviceTokens' => $serviceTokens,
            'system' => [
                'app_name' => config('app.name'),
                'environment' => app()->environment(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'timezone' => config('app.timezone'),
                'display_timezone' => display_timezone(),
                'image_disk' => $images->diskName(),
                'online_threshold' => config('inspection.online_threshold_minutes'),
                'image_max_kb' => config('inspection.image_max_kb'),
                'defect_classes' => config('inspection.defect_classes'),
                'counts' => [
                    'users' => User::query()->staff()->count(),
                    'devices' => Device::query()->count(),
                    'inspections' => Inspection::query()->count(),
                    'forecasts' => Forecast::query()->count(),
                ],
            ],
        ]);
    }

    public function revokeToken(PersonalAccessToken $token): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        abort_unless($token->tokenable?->isService(), 404);

        $label = $token->name.' @ '.$token->tokenable->name;
        $token->delete();

        return redirect()
            ->route('settings.index')
            ->with('status', 'Service token "'.$label.'" revoked.');
    }
}
