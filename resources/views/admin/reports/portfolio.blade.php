@extends('layouts.reports')

@section('report-title', 'Portfolio Summary')

@section('report-content')

{{-- ================= KPI CARDS ================= --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    {{-- Principal at Risk --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Principal at Risk</p>
        <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
            R{{ number_format($principalDeployed ?? 0, 2) }}
        </p>
    </div>

    {{-- Total Outstanding --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Total Collections Outstanding</p>
        <p class="text-2xl font-semibold text-yellow-600">
            R{{ number_format($totalOutstanding ?? 0, 2) }}
        </p>
    </div>

    {{-- Estimated Collectible --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Currently Overdue</p>
        <p class="text-2xl font-semibold text-green-600">
            R{{ number_format($overdueAmount ?? 0, 2) }}
        </p>
        <p class="text-xs text-gray-400">Provision-adjusted</p>
    </div>

    {{-- Current Month Collections --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Current Month Collections</p>
        <p class="text-2xl font-semibold text-blue-600">
            R{{ number_format($currentMonthCollections ?? 0, 2) }}
        </p>
        <p class="text-xs {{ $revenueGrowth >= 0 ? 'text-green-500' : 'text-red-500' }}">
            {{ number_format($revenueGrowth ?? 0, 2) }}% MoM
        </p>
    </div>

</div>

{{-- ================= RISK & PERFORMANCE ================= --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    {{-- PAR --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">PAR % (Overdue)</p>
        <p class="text-2xl font-semibold text-red-600">
            {{ number_format($parPercentage ?? 0, 2) }}%
        </p>
    </div>

    {{-- Collection Rate --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Collection Rate</p>
        <p class="text-2xl font-semibold text-green-600">
            {{ number_format($collectionRate ?? 0, 2) }}%
        </p>
    </div>

    {{-- Average Loan --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Average Loan Size</p>
        <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
            R{{ number_format($averageLoan ?? 0, 2) }}
        </p>
    </div>

    {{-- Portfolio Growth --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Portfolio Growth (MoM)</p>
        <p class="text-2xl font-semibold {{ $monthlyGrowth >= 0 ? 'text-purple-600' : 'text-red-600' }}">
            {{ number_format($monthlyGrowth ?? 0, 2) }}%
        </p>
    </div>

</div>

{{-- ================= STATUS BREAKDOWN ================= --}}
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <h3 class="text-md font-semibold mb-3 text-gray-700 dark:text-gray-200">
        Loan Status Breakdown
    </h3>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-right">Loans</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2 text-right">% of Principal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statusBreakdown as $row)
                <tr class="border-t">
                    <td class="px-4 py-2 capitalize">{{ $row->status }}</td>
                    <td class="px-4 py-2 text-right">{{ $row->loan_count }}</td>
                    <td class="px-4 py-2 text-right">
                        R{{ number_format($row->amount, 2) }}
                    </td>
                    <td class="px-4 py-2 text-right font-semibold">
                        {{ $principalDeployed > 0 
                            ? number_format(($row->amount / $principalDeployed) * 100, 2) 
                            : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ================= TOP 10 OVERDUE ================= --}}
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <h3 class="text-md font-semibold mb-3 text-gray-700 dark:text-gray-200">
        Top 10 Overdue Loans
    </h3>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-2">Loan ID</th>
                    <th class="px-4 py-2">Customer</th>
                    <th class="px-4 py-2 text-right">Outstanding</th>
                    <th class="px-4 py-2 text-right">Days Late</th>
                    <th class="px-4 py-2 text-right">% of Principal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topOverdue as $loan)
                <tr class="border-t">
                    <td class="px-4 py-2">#{{ $loan->id }}</td>
                    <td class="px-4 py-2">{{ $loan->customer_name }}</td>
                    <td class="px-4 py-2 text-right text-red-600">
                        R{{ number_format($loan->outstanding, 2) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        {{ $loan->days_late }} days
                    </td>
                    <td class="px-4 py-2 text-right font-semibold">
                        {{ $principalDeployed > 0 
                            ? number_format(($loan->outstanding / $principalDeployed) * 100, 2) 
                            : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const activity = @json($monthlyActivity);
    const revenueData = @json($monthlyRevenue);

    // ---- Normalize months ----
    const monthMap = {};

    activity.forEach(item => {
        monthMap[item.month] = {
            loans: item.num_loans,
            disbursed: item.total_disbursed,
            revenue: 0
        };
    });

    revenueData.forEach(item => {
        if (!monthMap[item.month]) {
            monthMap[item.month] = {
                loans: 0,
                disbursed: 0,
                revenue: item.total_collected
            };
        } else {
            monthMap[item.month].revenue = item.total_collected;
        }
    });

    const months = Object.keys(monthMap).sort();
    const loanCount = months.map(m => monthMap[m].loans);
    const disbursedAmount = months.map(m => monthMap[m].disbursed);
    const revenue = months.map(m => monthMap[m].revenue);

    const ctx = document.getElementById('portfolioTrendChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    type: 'bar',
                    label: 'Number of Loans',
                    data: loanCount,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    yAxisID: 'y1',
                },
                {
                    type: 'line',
                    label: 'Total Disbursed (R)',
                    data: disbursedAmount,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    yAxisID: 'y2',
                    tension: 0.3,
                    fill: true,
                },
                {
                    type: 'line',
                    label: 'Revenue (R)',
                    data: revenue,
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderDash: [6,4],
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    yAxisID: 'y2',
                    tension: 0.3,
                    fill: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y1: {
                    type: 'linear',
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Loans'
                    },
                    beginAtZero: true
                },
                y2: {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Amount (R)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'R ' + value.toLocaleString();
                        }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.yAxisID === 'y2') {
                                return context.dataset.label + ': R ' + context.raw.toLocaleString();
                            }
                            return context.dataset.label + ': ' + context.raw;
                        }
                    }
                },
                datalabels: {
                    display: false // turn on if you want values always visible
                }
            }
        },
        plugins: [ChartDataLabels]
    });

});
</script>
@endpush

@endsection
