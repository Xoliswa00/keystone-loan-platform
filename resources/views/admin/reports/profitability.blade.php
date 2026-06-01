@extends('layouts.reports')

@section('report-content')

<div class="container mx-auto px-4 py-6">
<div class="grid grid-cols-3 md:grid-cols-3 gap-6 mb-8">

    {{-- Total Principal --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Total Principal Deployed</p>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            R {{ number_format($totalPrincipal,2) }}
        </h2>
    </div>

    {{-- Expected Revenue --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Expected Income</p>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            R {{ number_format($expectedRevenue,2) }}
        </h2>
    </div>
    
    
       {{-- Collected Revenue --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Collected  Income</p>
        <h2 class="text-2xl font-bold text-green-600">
            R {{ number_format($Revenue,2) }}
        </h2>
    </div>
    
    
        {{-- Total Expected Cash Flow--}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500"> Total Expected Payments  </p>
        <h2 class="text-2xl font-bold text-green-600">
            R {{ number_format($TotalCashFlow,2) }}
        </h2>
    </div>

    {{-- Collected Revenue --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Collected Payments</p>
        <h2 class="text-2xl font-bold text-green-600">
            R {{ number_format($collectedRevenue,2) }}
        </h2>
    </div>

    {{-- Net Revenue --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Payments In Tracking</p>
        <h2 class="text-2xl font-bold text-blue-600">
            R {{ number_format(($totalOutstanding),2) }}
        </h2>
    </div>

    {{-- Portfolio Yield --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Portfolio Yield</p>
        <h2 class="text-2xl font-bold text-indigo-600">
            {{ number_format($portfolioYield,2) }}%
        </h2>
    </div>

    {{-- Average Margin --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <p class="text-sm text-gray-500">Average Loan Margin</p>
        <h2 class="text-2xl font-bold text-purple-600">
            {{ number_format($avgMargin,2) }}%
        </h2>
    </div>

</div>
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-8">
    <h3 class="text-lg font-semibold mb-4 text-gray-700 dark:text-gray-200">
        Capital Exposure Overview
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <p class="text-sm text-gray-500">Outstanding Capital</p>
            <p class="text-xl font-bold">
                R {{ number_format($CapitalOutstanding,2) }}
            </p>
        </div>
        
           <div>
            <p class="text-sm text-gray-500">Failed Capital</p>
            <p class="text-xl font-bold">
                R {{ number_format($FailedCapital,2) }}
            </p>
        </div>

        <div>
            <p class="text-sm text-gray-500">Overdue Exposure</p>
            <p class="text-xl font-bold text-red-600">
                R {{ number_format($overdueAmount,2) }}
            </p>
        </div>

        <div>
            <p class="text-sm text-gray-500">Risk Ratio</p>
            <p class="text-xl font-bold">
                {{ $totalOutstanding > 0 
                    ? number_format(($overdueAmount/$totalPrincipal)*100,2) 
                    : 0 }}%
            </p>
        </div>
        
         <div>
            <p class="text-sm text-gray-500">Failed Ratio</p>
            <p class="text-xl font-bold">
                {{ $totalOutstanding > 0 
                    ? number_format(($FailedCapital/$totalPrincipal)*100,2) 
                    : 0 }}%
            </p>
        </div>
    </div>
</div>
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-8">
    <h3 class="text-lg font-semibold mb-4 text-gray-700 dark:text-gray-200">
        Revenue Trend (Collected)
    </h3>

    <canvas id="revenueTrendChart" height="100"></canvas>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const months = @json($monthlyRevenue->pluck('month'));
    const revenue = @json($monthlyRevenue->pluck('total_revenue'));

    const ctx = document.getElementById('revenueTrendChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Collected Revenue',
                data: revenue,
                borderColor: 'rgba(75,192,192,1)',
                backgroundColor: 'rgba(75,192,192,0.2)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

});
</script>
@endpush
</div>
@endsection
