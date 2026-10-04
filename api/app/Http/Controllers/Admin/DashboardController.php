<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\RenderJob;
use App\Models\TranscribeJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Stats
        $stats = [
            'total_users' => User::count(),
            'total_jobs' => RenderJob::count(),
            'completed_jobs' => RenderJob::where('status', 'done')->count(),
            'failed_jobs' => RenderJob::where('status', 'failed')->count(),
            'queued_jobs' => RenderJob::where('status', 'queued')->count(),
            'processing_jobs' => RenderJob::where('status', 'processing')->count(),
            'total_storage_mb' => round(RenderJob::where('status', 'done')->sum('file_size_bytes') / 1048576, 1),
            'avg_render_time' => round(
                RenderJob::where('status', 'done')
                    ->whereNotNull('started_at')
                    ->whereNotNull('completed_at')
                    ->selectRaw(self::averageSecondsSql() . ' as avg_time')
                    ->value('avg_time') ?? 0
            ),
        ];

        // Recent jobs
        $recentJobs = RenderJob::with('user')
            ->latest()
            ->take(10)
            ->get();

        // Jobs per day (last 14 days)
        $jobsPerDay = RenderJob::where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Plans breakdown
        $planBreakdown = Plan::withCount('users')
            ->orderBy('sort_order')
            ->get();

        // Transcribe stats
        $transcribeStats = [
            'total' => TranscribeJob::count(),
            'done' => TranscribeJob::where('status', 'done')->count(),
            'processing' => TranscribeJob::where('status', 'processing')->count(),
            'queued' => TranscribeJob::where('status', 'queued')->count(),
            'failed' => TranscribeJob::where('status', 'failed')->count(),
        ];

        // Recent transcribe jobs
        $recentTranscribeJobs = TranscribeJob::with('user')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentJobs', 'jobsPerDay', 'planBreakdown', 'transcribeStats', 'recentTranscribeJobs'));
    }

    /** Average render duration in seconds; the expression differs between MySQL and PostgreSQL. */
    public static function averageSecondsSql(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (completed_at - started_at)))'
            : 'AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))';
    }
}
