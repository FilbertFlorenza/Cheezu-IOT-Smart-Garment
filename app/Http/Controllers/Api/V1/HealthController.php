<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    public function __invoke(ImageStorageService $images): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->select('select 1');
        } catch (\Throwable $exception) {
            report($exception);
            $database = 'error';
        }

        $healthy = $database === 'ok';

        return response()->json([
            'success' => $healthy,
            'data' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $database,
                    'storage_disk' => $images->diskName(),
                ],
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->attributes->get('request_id') ?? (string) Str::ulid(),
            ],
        ], $healthy ? 200 : 503);
    }
}
