<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;

class ExpirePlans extends Command
{
    protected $signature = 'plans:expire';
    protected $description = 'Downgrade users with expired plans back to Free';

    public function handle(): int
    {
        $freePlan = Plan::where('slug', 'free')->first();

        $expired = User::whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->when($freePlan, fn ($q) => $q->where('plan_id', '!=', $freePlan->id))
            ->get();

        // Installations without a Free plan (e.g. a single Enterprise plan) have
        // nothing to downgrade to; that is only an error when someone has expired.
        if ($expired->isEmpty()) {
            $this->info('Done. 0 user(s) downgraded.');
            return 0;
        }

        if (!$freePlan) {
            $this->error("Free plan not found! {$expired->count()} expired user(s) left unchanged.");
            return 1;
        }

        foreach ($expired as $user) {
            $oldPlan = $user->plan?->name ?? 'Unknown';
            $user->update([
                'plan_id' => $freePlan->id,
                'plan_expires_at' => null,
            ]);
            $this->info("Downgraded {$user->name} ({$user->email}) from {$oldPlan} → Free");
        }

        $this->info("Done. {$expired->count()} user(s) downgraded.");
        return 0;
    }
}
