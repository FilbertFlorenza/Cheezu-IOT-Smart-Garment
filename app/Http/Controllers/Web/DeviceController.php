<?php

namespace App\Http\Controllers\Web;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDeviceRequest;
use App\Http\Requests\Web\UpdateDeviceRequest;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Device::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
        ]);

        $devices = Device::query()
            ->filter($filters)
            ->withCount('inspections')
            ->with('latestInspection')
            ->orderBy('device_code')
            ->paginate(10)
            ->withQueryString();

        return view('devices.index', [
            'devices' => $devices,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Device::class);

        return view('devices.create');
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $device = Device::query()->create($request->validated());

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device '.$device->device_code.' registered.');
    }

    public function show(Device $device): View
    {
        $this->authorize('view', $device);

        $counts = $device->inspections()
            ->selectRaw('overall_status, COUNT(*) as aggregate')
            ->groupBy('overall_status')
            ->pluck('aggregate', 'overall_status');

        return view('devices.show', [
            'device' => $device,
            'inspections' => $device->inspections()
                ->withCount('detections')
                ->latest('inspected_at')
                ->paginate(10),
            'totalInspections' => (int) $counts->sum(),
            'passCount' => (int) $counts->get('PASS', 0),
            'defectCount' => (int) $counts->get('DEFECT', 0),
        ]);
    }

    public function edit(Device $device): View
    {
        $this->authorize('update', $device);

        return view('devices.edit', ['device' => $device]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->validated());

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device '.$device->device_code.' updated.');
    }

    public function deactivate(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->update(['status' => DeviceStatus::Inactive]);

        return redirect()
            ->back()
            ->with('status', 'Device '.$device->device_code.' deactivated.');
    }

    public function activate(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->update(['status' => DeviceStatus::Active]);

        return redirect()
            ->back()
            ->with('status', 'Device '.$device->device_code.' activated.');
    }
}
