<?php

use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\ForecastIngestionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InspectionController;
use App\Http\Controllers\Api\V1\InspectionResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class)
        ->middleware('throttle:health')
        ->name('api.v1.health');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // Machine ingestion endpoints.
        Route::post('inspections/results', [InspectionResultController::class, 'store'])
            ->middleware(['abilities:inspections:write', 'throttle:inspections'])
            ->name('api.v1.inspections.results');

        Route::post('forecasts/cloud', [ForecastIngestionController::class, 'storeCloud'])
            ->middleware(['abilities:forecasts:cloud:write', 'throttle:forecasts'])
            ->name('api.v1.forecasts.cloud.store');

        Route::post('forecasts/network', [ForecastIngestionController::class, 'storeNetwork'])
            ->middleware(['abilities:forecasts:network:write', 'throttle:forecasts'])
            ->name('api.v1.forecasts.network.store');

        // Read endpoints for integrations and reporting.
        Route::middleware(['abilities:read', 'throttle:api-read'])->group(function () {
            Route::get('devices', [DeviceController::class, 'index'])->name('api.v1.devices.index');
            Route::get('inspections', [InspectionController::class, 'index'])->name('api.v1.inspections.index');
            Route::get('inspections/{inspection}', [InspectionController::class, 'show'])->name('api.v1.inspections.show');
            Route::get('forecasts/cloud', [ForecastController::class, 'cloud'])->name('api.v1.forecasts.cloud.index');
            Route::get('forecasts/network', [ForecastController::class, 'network'])->name('api.v1.forecasts.network.index');
        });
    });
});
