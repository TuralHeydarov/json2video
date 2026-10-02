<?php

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlanRequestController;
use App\Http\Controllers\Admin\RenderJobController;
use App\Http\Controllers\Admin\TranscribeJobController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserDashboardController;
use Illuminate\Support\Facades\Route;

// ─── API Documentation ───────────────────────
Route::get('/docs', function () {
    return view('docs');
})->name('docs');

// ─── Public Landing Page ──────────────────────
Route::get('/', function () {
    return view('portal.landing');
});

// ─── Expired Video Page ──────────────────────
Route::get('/video-expired/{filename}', function (string $filename) {
    $job = \App\Models\RenderJob::where('status', 'expired')
        ->whereRaw("output_url LIKE ?", ["%{$filename}"])
        ->first();

    return response()->view('portal.video-expired', [
        'filename' => $filename,
        'job' => $job,
    ], 410); // 410 Gone
});

// ─── User Auth ────────────────────────────────
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/shared/start', [\App\Http\Controllers\SharedAuthController::class, 'start'])->middleware('throttle:10,1');
Route::get('/shared/callback', [\App\Http\Controllers\SharedAuthController::class, 'callback'])->middleware('throttle:10,1');
Route::get('/shared/account', [\App\Http\Controllers\SharedAuthController::class, 'linkForm'])->middleware('auth');
Route::post('/shared/link/start', [\App\Http\Controllers\SharedAuthController::class, 'linkStart'])->middleware(['auth', 'throttle:10,1']);
Route::post('/shared/logout', [\App\Http\Controllers\SharedAuthController::class, 'logout'])->middleware('throttle:10,1');

// ─── User Portal (auth required) ──────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/plans', [UserDashboardController::class, 'plans'])->name('plans');
    Route::post('/plans/request', [UserDashboardController::class, 'requestPlan'])->name('plans.request');
    Route::post('/api-keys', [UserDashboardController::class, 'createApiKey'])->name('api-keys.create');
    Route::delete('/api-keys/{id}', [UserDashboardController::class, 'deleteApiKey'])->name('api-keys.delete');
});

// ─── Admin Panel ──────────────────────────────
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'login']);

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

        // Users
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::get('/users/{id}', [UserController::class, 'show'])->name('admin.users.show');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('admin.users.delete');

        // Plans
        Route::get('/plans', [PlanController::class, 'index'])->name('admin.plans');
        Route::post('/plans', [PlanController::class, 'store'])->name('admin.plans.store');
        Route::put('/plans/{id}', [PlanController::class, 'update'])->name('admin.plans.update');
        Route::delete('/plans/{id}', [PlanController::class, 'destroy'])->name('admin.plans.delete');

        // Plan Requests
        Route::get('/plan-requests', [PlanRequestController::class, 'index'])->name('admin.plan-requests');
        Route::post('/plan-requests/{id}/approve', [PlanRequestController::class, 'approve'])->name('admin.plan-requests.approve');
        Route::post('/plan-requests/{id}/reject', [PlanRequestController::class, 'reject'])->name('admin.plan-requests.reject');

        // Render Jobs
        Route::get('/jobs', [RenderJobController::class, 'index'])->name('admin.jobs');
        Route::get('/jobs/{id}', [RenderJobController::class, 'show'])->name('admin.jobs.show');
        Route::delete('/jobs/{id}', [RenderJobController::class, 'destroy'])->name('admin.jobs.delete');

        // Transcribe Jobs
        Route::get('/transcribe-jobs', [TranscribeJobController::class, 'index'])->name('admin.transcribe-jobs');
        Route::get('/transcribe-jobs/{id}', [TranscribeJobController::class, 'show'])->name('admin.transcribe-jobs.show');
        Route::delete('/transcribe-jobs/{id}', [TranscribeJobController::class, 'destroy'])->name('admin.transcribe-jobs.delete');

        // Templates
        Route::get('/templates', [TemplateController::class, 'index'])->name('admin.templates');
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('admin.templates.delete');

        // Render tool
        Route::get('/render', function () {
            return view('admin.render');
        })->name('admin.render');
    });
});
