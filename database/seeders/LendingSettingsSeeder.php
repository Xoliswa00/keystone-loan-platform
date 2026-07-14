<?php

namespace Database\Seeders;

use App\Models\LendingSetting;
use Illuminate\Database\Seeder;

class LendingSettingsSeeder extends Seeder
{
    /**
     * Same defaults previously inserted by 2026_07_12_194727_create_lending_settings_table.php's
     * up() method, now that migration only holds schema.
     */
    public function run(): void
    {
        if (LendingSetting::count() > 0) {
            return;
        }

        LendingSetting::create([
            'max_active_loans' => 1,
            'rejection_cooldown_days' => 30,
            'affordability_ratio' => 0.30,
            'clo_risk_review_threshold' => 40,
            'clo_risk_escalate_threshold' => 75,
            'ifrs9_stage2_dpd' => 30,
            'ifrs9_stage3_dpd' => 90,
            'ifrs9_writeoff_dpd' => 180,
            'ifrs9_stage1_rate' => 0.02,
            'ifrs9_stage2_rate' => 0.20,
            'ifrs9_stage3_rate' => 0.80,
            'arrears_second_notice_dpd' => 60,
            'payment_reminder_days_before_due' => 3,
        ]);
    }
}
