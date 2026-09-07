<?php

namespace Tests\Feature;

use App\Jobs\GenerateClientStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateClientStatementTest extends TestCase
{
    use DatabaseTransactions;

    private function makeClient(): User
    {
        return User::create([
            'name' => 'Test Client',
            'email' => 'statement-'.uniqid('', true).'@example.com',
            'password' => bcrypt('password'),
            'address' => '1 Test Street',
            'phone' => (string) random_int(1000000000, 9999999999),
            'salary_payment_day' => 25,
            'ID_copy' => 'id_copies/test.pdf',
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
        ]);
    }

    /**
     * Regression test for a live bug: the job built its header PDF variables
     * from a bare DB::table('companies')->first() stdClass and only passed
     * `company`/`ncr_number`, but agreements.partials.header (shared with the
     * loan agreement/pre-agreement/settlement PDFs) needs `companyAddress`,
     * `vat_number`, `contact_phone`, `contact_email` and `generatedAt` as
     * top-level variables too — it threw "Undefined variable $companyAddress"
     * for every statement, even with zero loans/repayments.
     */
    public function test_generating_a_statement_does_not_throw_on_missing_header_variables(): void
    {
        Storage::fake('local');

        $client = $this->makeClient();

        (new GenerateClientStatement($client->id))->handle();

        $expectedPath = 'statements/'.$client->id.'/KCP-Statement-'
            .str_pad((string) $client->id, 6, '0', STR_PAD_LEFT).'-'.now()->format('Y-m').'.pdf';

        Storage::disk('local')->assertExists($expectedPath);
    }
}
