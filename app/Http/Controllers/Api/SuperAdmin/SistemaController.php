<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SistemaController extends Controller
{
    /**
     * High-level system health: environment, DB connectivity, queue/jobs.
     */
    public function status(): JsonResponse
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

        return response()->json([
            'app' => [
                'env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'version' => app()->version(),
                'php' => PHP_VERSION,
            ],
            'database' => [
                'ok' => $dbOk,
                'driver' => config('database.default'),
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ],
        ]);
    }

    /**
     * Tail of the Laravel log file.
     */
    public function logs(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return response()->json(['lines' => [], 'message' => 'No hay archivo de log.']);
        }

        $content = File::get($path);
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $tail = array_slice($lines, -300);

        return response()->json(['lines' => array_values(array_filter($tail))]);
    }

    /**
     * Clear application caches (config, route, view, application cache).
     */
    public function cacheClear(): JsonResponse
    {
        Artisan::call('optimize:clear');

        return response()->json([
            'cleared' => true,
            'output' => trim(Artisan::output()),
        ]);
    }

    /**
     * List failed queue jobs (most recent first).
     */
    public function failedJobs(): JsonResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return response()->json(['data' => []]);
        }

        $jobs = DB::table('failed_jobs')->orderByDesc('id')->limit(100)->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid ?? null,
                    'queue' => $job->queue ?? null,
                    'job' => $payload['displayName'] ?? 'desconocido',
                    'exception' => mb_substr((string) ($job->exception ?? ''), 0, 500),
                    'failed_at' => $job->failed_at ?? null,
                ];
            });

        return response()->json(['data' => $jobs]);
    }
}
