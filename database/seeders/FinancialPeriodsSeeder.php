<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinancialPeriodsSeeder extends Seeder
{
    /**
     * Same rolling window previously inserted by 2026_06_02_000018_create_financial_periods_table.php's
     * up() method, now that migration only holds schema — deliberately
     * relative to now() at seed-time (3 months back through 1 month
     * forward), not fixed dates, matching the original migration's intent.
     * insertOrIgnore means this is also safe to re-run.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonths(3);

        for ($i = 0; $i <= 4; $i++) {
            $d = $start->copy()->addMonths($i);
            DB::table('financial_periods')->insertOrIgnore([
                'period' => $d->format('Y-m'),
                'fiscal_year' => $d->year,
                'fiscal_month' => $d->month,
                'is_year_end' => $d->month === 12,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
