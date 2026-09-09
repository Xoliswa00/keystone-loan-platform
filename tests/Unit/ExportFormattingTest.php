<?php

namespace Tests\Unit;

use App\Models\ExportProfile;
use App\Services\Export\Formatters\CsvFormatter;
use App\Support\ColumnFormatter;
use Tests\TestCase;

class ExportFormattingTest extends TestCase
{
    public function test_column_formatter_tokens(): void
    {
        $this->assertSame('', ColumnFormatter::apply(null, 'string'));
        $this->assertSame('1234.50', ColumnFormatter::apply(1234.5, 'money'));
        $this->assertSame('7', ColumnFormatter::apply('6.7', 'integer'));
        $this->assertSame('ABC', ColumnFormatter::apply('abc', 'upper'));
        $this->assertSame('2026-08-31', ColumnFormatter::apply('2026-08-31 23:59:59', 'date:Y-m-d'));
        $this->assertSame('plain', ColumnFormatter::apply('plain', null));
    }

    public function test_column_formatter_map_token_uses_config_table(): void
    {
        config(['exports.maps.status' => ['Open' => 'A', 'WrittenOff' => 'W']]);

        $this->assertSame('A', ColumnFormatter::apply('Open', 'map:status'));
        $this->assertSame('Unmapped', ColumnFormatter::apply('Unmapped', 'map:status'));
    }

    private function profile(array $overrides = []): ExportProfile
    {
        return new ExportProfile(array_merge([
            'format' => 'csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'include_header' => true,
            'line_ending' => 'lf',
            'columns' => [
                ['source' => 'account_no', 'label' => 'Account', 'format' => 'string'],
                ['source' => 'arrears_amount', 'label' => 'Arrears', 'format' => 'money'],
                ['source' => 'days_past_due', 'label' => 'DPD', 'format' => 'integer'],
            ],
        ], $overrides));
    }

    public function test_csv_respects_column_order_labels_and_formats(): void
    {
        $csv = (new CsvFormatter)->format([
            ['account_no' => 'KL-000042', 'days_past_due' => 12, 'arrears_amount' => 99.5, 'ignored' => 'x'],
        ], $this->profile());

        $this->assertSame(
            "Account,Arrears,DPD\nKL-000042,99.50,12\n",
            $csv
        );
    }

    public function test_csv_guards_against_formula_injection(): void
    {
        $csv = (new CsvFormatter)->format([
            ['account_no' => '=cmd()', 'arrears_amount' => 0, 'days_past_due' => 0],
        ], $this->profile(['include_header' => false]));

        $this->assertStringStartsWith("'=cmd(),0.00,0", $csv);
    }

    public function test_csv_honours_custom_delimiter_and_crlf(): void
    {
        $csv = (new CsvFormatter)->format([
            ['account_no' => 'A', 'arrears_amount' => 1, 'days_past_due' => 2],
        ], $this->profile(['delimiter' => '|', 'line_ending' => 'crlf', 'include_header' => false]));

        $this->assertSame("A|1.00|2\r\n", $csv);
    }
}
