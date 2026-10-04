<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (!preg_match('/rehearsal|test_/', getenv('DB_DATABASE') ?: '')) {
            throw new \RuntimeException('Dashboard tests require an isolated PostgreSQL fixture database');
        }
        parent::setUp();
    }

    public function test_average_render_time_query_runs_on_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $user = DB::table('users')->insertGetId(['name' => 'fixture', 'email' => 'fixture@example.invalid',
            'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([10, 30] as $seconds) {
            DB::table('render_jobs')->insert(['id' => (string) Str::uuid(), 'user_id' => $user, 'status' => 'done',
                'payload' => '{}', 'started_at' => '2026-10-04 00:00:00',
                'completed_at' => date('Y-m-d H:i:s', strtotime('2026-10-04 00:00:00') + $seconds),
                'created_at' => now(), 'updated_at' => now()]);
        }
        $avg = DB::table('render_jobs')->where('status', 'done')
            ->selectRaw(DashboardController::averageSecondsSql() . ' as avg_time')->value('avg_time');
        $this->assertEquals(20, round($avg));
    }
}
