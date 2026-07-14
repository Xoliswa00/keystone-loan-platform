<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * NCR quarterly return export — shared by `keystone:ncr-export` and the
 * admin/ncr-export web download. Previously the web version was a
 * divergent, thinner reimplementation missing half the NCR-required
 * columns (age, DTI ratio, credit bureau score/provider, NCA credit type,
 * remaining balance, account status); this is the single source of truth
 * both now use.
 */
class NcrExportService
{
    const HEADERS = [
        'Application ID', 'Loan ID', 'Customer Code', 'ID Number', 'Full Name',
        'Age', 'Gender', 'Loan Type', 'NCA Credit Type', 'NCR Purpose Code',
        'Principal Amount', 'Initiation Fee', 'Service Fee', 'Interest Amount',
        'Total Credit Cost', 'Term (Months)', 'Disbursed Date', 'Loan Status',
        'Remaining Balance', 'Account Status', 'DTI Ratio', 'Credit Score', 'Bureau Provider',
    ];

    /**
     * @throws \InvalidArgumentException if $quarter isn't YYYY-QN
     */
    public function loansForQuarter(string $quarter): Collection
    {
        if (! preg_match('/(\d{4})-Q([1-4])/', $quarter, $m)) {
            throw new \InvalidArgumentException('Invalid quarter format. Use YYYY-QN (e.g. 2026-Q1)');
        }

        $year = (int) $m[1];
        $q = (int) $m[2];
        $from = Carbon::create($year, ($q - 1) * 3 + 1, 1)->startOfMonth();
        $to = $from->copy()->addMonths(3)->subDay()->endOfDay();

        return DB::table('loan_applications as a')
            ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'u.id')
            ->leftJoin('loan_fees as lf', 'lf.loan_application_id', '=', 'a.id')
            ->leftJoin('loan_products as lp', 'lp.id', '=', 'a.loan_product_id')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->select(
                'a.id as application_id',
                'l.id as loan_id',
                'c.customer_code',
                'u.ID_Number as id_number',
                'u.name as full_name',
                DB::raw('TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age'),
                'u.gender',
                'a.loan_type',
                'a.nca_credit_type',
                'a.ncr_purpose_code',
                'l.loan_amount as principal_amount',
                'lf.initiation_fee',
                'lf.service_fee',
                'lf.interest_amount',
                'lf.total_due as total_credit_cost',
                'l.loan_term_months as term_months',
                'l.disbursed_date',
                'l.status as loan_status',
                'l.remaining_balance',
                DB::raw("CASE WHEN l.status IN ('settled','written_off') THEN 'Closed' ELSE 'Open' END as account_status"),
                'a.affordability_dti_ratio as dti_ratio',
                'a.credit_bureau_score',
                'a.credit_bureau_provider'
            )
            ->orderBy('l.disbursed_date')
            ->get();
    }

    public function toCsv(Collection $loans): string
    {
        $csv = implode(',', self::HEADERS)."\n";

        foreach ($loans as $loan) {
            $csv .= implode(',', [
                $loan->application_id,
                $loan->loan_id,
                $loan->customer_code ?? '',
                '"'.($loan->id_number ?? '').'"',
                '"'.str_replace('"', '""', $loan->full_name ?? '').'"',
                $loan->age ?? '',
                $loan->gender ?? '',
                $loan->loan_type ?? '',
                '"'.($loan->nca_credit_type ?? '').'"',
                $loan->ncr_purpose_code ?? 'OTHER',
                number_format($loan->principal_amount, 2, '.', ''),
                number_format($loan->initiation_fee ?? 0, 2, '.', ''),
                number_format($loan->service_fee ?? 0, 2, '.', ''),
                number_format($loan->interest_amount ?? 0, 2, '.', ''),
                number_format($loan->total_credit_cost ?? 0, 2, '.', ''),
                $loan->term_months ?? 1,
                $loan->disbursed_date ?? '',
                $loan->loan_status ?? '',
                number_format($loan->remaining_balance ?? 0, 2, '.', ''),
                $loan->account_status ?? '',
                number_format($loan->dti_ratio ?? 0, 2, '.', ''),
                $loan->credit_bureau_score ?? '',
                $loan->credit_bureau_provider ?? '',
            ])."\n";
        }

        return $csv;
    }
}
