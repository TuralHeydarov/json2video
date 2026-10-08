<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePlansTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Plan $plan, $expiresAt): User
    {
        static $n = 0;
        $n++;
        return User::create(['name' => "fixture{$n}", 'email' => "fixture{$n}@invalid.test",
            'password' => 'fixture', 'plan_id' => $plan?->id, 'plan_expires_at' => $expiresAt]);
    }

    public function test_succeeds_without_free_plan_when_nobody_expired(): void
    {
        $enterprise = Plan::create(['name' => 'Enterprise', 'slug' => 'enterprise']);
        $this->user($enterprise, null);
        $this->user($enterprise, now()->addDay());

        $this->artisan('plans:expire')->assertExitCode(0);
    }

    public function test_fails_without_free_plan_when_someone_expired(): void
    {
        $enterprise = Plan::create(['name' => 'Enterprise', 'slug' => 'enterprise']);
        $user = $this->user($enterprise, now()->subDay());

        $this->artisan('plans:expire')->assertExitCode(1);
        $this->assertSame($enterprise->id, $user->fresh()->plan_id);
    }

    public function test_downgrades_expired_users_to_free(): void
    {
        $free = Plan::create(['name' => 'Free', 'slug' => 'free']);
        $pro = Plan::create(['name' => 'Pro', 'slug' => 'pro']);
        $expired = $this->user($pro, now()->subDay());
        $active = $this->user($pro, now()->addDay());

        $this->artisan('plans:expire')->assertExitCode(0);
        $this->assertSame($free->id, $expired->fresh()->plan_id);
        $this->assertNull($expired->fresh()->plan_expires_at);
        $this->assertSame($pro->id, $active->fresh()->plan_id);
    }
}
