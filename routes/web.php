<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ForecastController;
use App\Http\Controllers\Web\InspectionController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('/inspections/{inspection}/image', [InspectionController::class, 'image'])->name('inspections.image');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::patch('/devices/{device}/deactivate', [DeviceController::class, 'deactivate'])->name('devices.deactivate');
    Route::patch('/devices/{device}/activate', [DeviceController::class, 'activate'])->name('devices.activate');

    Route::get('/forecasts/cloud', [ForecastController::class, 'cloud'])->name('forecasts.cloud');
    Route::get('/forecasts/network', [ForecastController::class, 'network'])->name('forecasts.network');
    Route::get('/forecasts/{forecast}', [ForecastController::class, 'show'])->name('forecasts.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::delete('/settings/tokens/{token}', [SettingsController::class, 'revokeToken'])->name('settings.tokens.revoke');
    });
});

require __DIR__.'/auth.php';
