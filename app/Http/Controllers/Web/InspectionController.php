<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Inspection;
use App\Services\ImageStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Inspection::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:PASS,DEFECT'],
            'defect_class' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $inspections = Inspection::query()
            ->with('device')
            ->withCount('detections')
            ->filter($filters)
            ->latest('inspected_at')
            ->paginate(15)
            ->withQueryString();

        return view('inspections.index', [
            'inspections' => $inspections,
            'devices' => Device::query()->orderBy('device_code')->get(['id', 'device_code', 'device_name']),
            'defectClasses' => $this->defectClasses(),
            'filters' => $filters,
        ]);
    }

    public function show(Inspection $inspection): View
    {
        $this->authorize('view', $inspection);

        $inspection->load(['device', 'detections']);

        return view('inspections.show', [
            'inspection' => $inspection,
            'imageUrl' => $inspection->image_path !== null
                ? route('inspections.image', $inspection)
                : null,
        ]);
    }

    public function image(
        Inspection $inspection,
        ImageStorageService $images,
    ): BinaryFileResponse|RedirectResponse {
        $this->authorize('view', $inspection);

        abort_if($inspection->image_path === null, 404);
        abort_unless($images->disk()->exists($inspection->image_path), 404);

        if ($images->isLocal()) {
            return response()->file($images->absolutePath($inspection->image_path), [
                'Content-Type' => $inspection->image_mime ?? 'image/jpeg',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        $url = $images->temporaryUrl($inspection->image_path);
        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    /**
     * @return array<int, string>
     */
    private function defectClasses(): array
    {
        $configured = config('inspection.defect_classes');

        $stored = Inspection::query()
            ->join('inspection_detections', 'inspection_detections.inspection_id', '=', 'inspections.id')
            ->distinct()
            ->pluck('inspection_detections.defect_class')
            ->all();

        return array_values(array_unique(array_merge($configured, $stored)));
    }
}
