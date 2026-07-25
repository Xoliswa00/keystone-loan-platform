<?php

namespace Tests\Feature;

use App\Models\branches;
use App\Models\chart_of_accounts;
use App\Models\companies;
use App\Models\gl_accounts;
use App\Services\GLPostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use Tests\TestCase;

/**
 * GLPostingService::resolveBalanceDirection() used to read account_type
 * literally against ['asset','expense'] — a granular subtype ('bank',
 * 'receivable', 'contra_asset', ...) that never equals those strings, so
 * every real asset account was credit-normal instead of debit-normal. The
 * fix reads account_category instead, but a contra-asset (account_category
 * = 'asset', account_type = 'contra_asset') must stay credit-normal despite
 * its category — these tests pin both halves of that behaviour so neither
 * regresses silently again.
 */
class GLPostingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAccount(string $category, string $type, string $code): gl_accounts
    {
        $company = companies::first() ?? companies::create(['name' => 'Test Company']);

        $branch = branches::firstOrCreate(
            ['branch_code' => 'TST'],
            ['branch_name' => 'Test Branch', 'company_id' => $company->id]
        );

        $coa = chart_of_accounts::firstOrCreate(
            ['account_code' => $code],
            [
                'account_category' => $category,
                'account_group' => 'Test Group',
                'account_type' => $type,
                'statement_section' => 'Balance Sheet',
            ]
        );

        return gl_accounts::firstOrCreate(
            ['full_account_no' => $code.'-TST'],
            [
                'chart_of_account_id' => $coa->id,
                'branch_id' => $branch->id,
                'opening_balance' => 0,
                'current_balance' => 100,
            ]
        );
    }

    private function updatedBalance(GLPostingService $service, float $current, float $debit, float $credit, string $direction): float
    {
        $method = (new ReflectionClass($service))->getMethod('updatedBalance');

        return $method->invoke($service, $current, $debit, $credit, $direction);
    }

    public function test_real_asset_account_is_debit_normal(): void
    {
        $bank = $this->makeAccount('asset', 'bank', '1100-TEST');
        $service = new GLPostingService;

        $direction = $service->resolveBalanceDirection($bank);
        $balance = $this->updatedBalance($service, 100.0, 50.0, 0.0, $direction);

        $this->assertEquals(150.0, $balance, 'A debit to a real asset account must increase its balance.');
    }

    public function test_contra_asset_account_stays_credit_normal_despite_asset_category(): void
    {
        $allowance = $this->makeAccount('asset', 'contra_asset', '1240-TEST');
        $service = new GLPostingService;

        $direction = $service->resolveBalanceDirection($allowance);
        $balance = $this->updatedBalance($service, 100.0, 0.0, 50.0, $direction);

        $this->assertEquals(150.0, $balance,
            'A credit to a contra-asset account (e.g. Allowance for Credit Losses) must increase its balance, not decrease it.');
    }

    public function test_liability_account_is_credit_normal(): void
    {
        $liability = $this->makeAccount('liability', 'payable', '2999-TEST');
        $service = new GLPostingService;

        $direction = $service->resolveBalanceDirection($liability);
        $balance = $this->updatedBalance($service, 100.0, 0.0, 50.0, $direction);

        $this->assertEquals(150.0, $balance, 'A credit to a liability account must increase its balance.');
    }
}
