<?php

return [

    /*
    |--------------------------------------------------------------------------
    | National Credit Act (NCA) Prescribed Maximums
    |--------------------------------------------------------------------------
    |
    | Source: National Credit Regulations, 2006 (as amended), Regulation 42
    | and Table A/Table B (fees), plus s.103(5) (in duplum rule).
    |
    | interest_rate_cap_monthly: the "short-term credit transaction" ceiling
    | of 5% per month (simple, non-compounding) — Regulation 42(1) Table A.
    | This is the correct cap for short-term/microloan products. Longer-term
    | or larger unsecured credit is capped instead at (repo rate x 2.2) + 20%
    | per annum, which moves with the SARB repo rate — if this product range
    | is ever used for that credit category, this value must be recalculated
    | against the current repo rate and confirmed with compliance/legal
    | before use; it is NOT safe to assume the short-term cap applies.
    |
    | initiation_fee_cap: R150 + 10% of principal over R1,000, capped at
    | R1,050, excl. VAT — Regulation 42(1).
    |
    | monthly_service_fee_cap: R60 excl. VAT — Regulation 42(1). May only be
    | escalated annually at (repo rate + 10%); do not raise this default
    | without a documented escalation calculation.
    |
    | in_duplum_multiple: s.103(5) — interest, default charges, and fees
    | that accrue while a consumer is in default may never exceed the
    | unpaid principal balance at the moment default occurred (i.e. a 1.0x
    | multiple of that balance).
    |
    */

    'interest_rate_cap_monthly'   => (float) env('NCA_INTEREST_RATE_CAP_MONTHLY', 0.05),
    'initiation_fee_flat_cap'     => (float) env('NCA_INITIATION_FEE_FLAT_CAP', 150.00),
    'initiation_fee_absolute_cap' => (float) env('NCA_INITIATION_FEE_ABSOLUTE_CAP', 1050.00),
    'monthly_service_fee_cap'     => (float) env('NCA_MONTHLY_SERVICE_FEE_CAP', 60.00),
    'in_duplum_multiple'          => (float) env('NCA_IN_DUPLUM_MULTIPLE', 1.0),

];
