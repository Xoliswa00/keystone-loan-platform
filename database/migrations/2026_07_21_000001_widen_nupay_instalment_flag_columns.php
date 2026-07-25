<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * original_instalment and rescheduled_instalment were typed as integer
     * on the assumption they held instalment sequence numbers — but real
     * NuPay exports send literal 'Yes'/'No' flag strings for these two
     * columns ("is this the original instalment", "has this been
     * rescheduled"), distinct from `instalment`/`total_instalments`, which
     * really are sequence numbers and are left untouched. Every row from a
     * real export was failing with "Incorrect integer value: 'Yes'/'No'".
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE nupay_transactions_stagings MODIFY COLUMN original_instalment VARCHAR(20) NULL');
        DB::statement('ALTER TABLE nupay_transactions_stagings MODIFY COLUMN rescheduled_instalment VARCHAR(20) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE nupay_transactions_stagings MODIFY COLUMN original_instalment INT NULL');
        DB::statement('ALTER TABLE nupay_transactions_stagings MODIFY COLUMN rescheduled_instalment INT NULL');
    }
};
