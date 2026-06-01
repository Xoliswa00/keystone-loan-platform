@extends('layouts.reports')

@section('report-title', 'Reviewer Governance Profile')

@section('report-content')

<div class="p-6 space-y-10">

    {{-- ================= HEADER ================= --}}
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold">{{ $reviewer->reviewer }}</h2>
            <p class="text-gray-500 text-sm">
                Credit Behaviour & Risk Profile
            </p>
        </div>

        <div class="text-sm">
            <p class="text-gray-500 text-sm">PARTICIPATION</p>
            <br>
            <span class="px-3 py-1 rounded font-semibold
                {{ $reviewer->risk_flag == 'AGGRESSIVE' ? 'bg-red-100 text-red-700' : '' }}
                {{ $reviewer->risk_flag == 'HIGH DEFAULT' ? 'bg-orange-100 text-orange-700' : '' }}
                {{ $reviewer->risk_flag == 'LOW PARTICIPATION' ? 'bg-yellow-100 text-yellow-700' : '' }}
                {{ $reviewer->risk_flag == 'NORMAL' ? 'bg-green-100 text-green-700' : '' }}">
                {{ $reviewer->risk_flag }}
            </span>
        </div>
    </div>

    {{-- ================= KPI CARDS ================= --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <p class="text-sm text-gray-500">Participation</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ number_format($reviewer->participation_pct ?? 0, 2) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <p class="text-sm text-gray-500">Approval Rate</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ number_format($reviewer->approval_rate ?? 0, 2) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <p class="text-sm text-gray-500">Default Rate</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ number_format($reviewer->default_rate ?? 0, 2) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <p class="text-sm text-gray-500">Risk Exposure</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ number_format($reviewer->risky_exposure ?? 0, 2) }}
            </p>
        </div>

    </div>

    {{-- ================= DEFAULTS ================= --}}
    <div class="bg-white shadow rounded-xl p-6">
    <h3 class="font-semibold mb-4 text-red-600">
        High Risk Loans Approved
    </h3>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 divide-y divide-gray-200">
            <thead class="bg-red-50 text-red-700 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Loan</th>
                    <th class="px-4 py-3 text-left">Client Name</th>
                          <th class="px-4 py-3 text-left">CLF Number</th>
                     <th class="px-4 py-3 text-left">Comment on the Loan</th>

              
                    <th class="px-4 py-3 text-right">Outstanding</th>
                    <th class="px-4 py-3 text-left">Last Update</th>
                    <th class="px-4 py-3 text-center">View</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
                @forelse($defaults as $d)
                    <tr class="hover:bg-red-50 transition">
                        <td class="px-4 py-3 font-semibold">#{{ $d->id }}</td>
                        <td class="px-4 py-3">
                            <button 
                                class="text-blue-600 hover:underline" 
                                onclick="showCustomer({{ $d->cust }})">
                                {{ $d->name }}
                            </button>
                        </td>
                        <td class="px-4 py-3">{{ $d->customer_code }}</td>
                                                                                                <td class="px-4 py-3">{{ $d->reason }}</td>


                        <td class="px-4 py-3 text-right text-red-600 font-semibold">
                            R {{ number_format($d->remaining_balance,2) }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ \Carbon\Carbon::parse($d->updated_at)->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                        <a href="{{ route('customers.show', $d->cust) }}" class="block px-4 py-2 text-sm text-blue-600 hover:bg-gray-100">View</a>

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                            No high-risk loans
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

    {{-- ================= TREND CHART ================= --}}
    <div class="bg-white shadow rounded-xl p-6">
        <h3 class="font-semibold mb-4">Monthly Behaviour Trend</h3>
        <div id="trendChart" style="min-height:350px;"></div>
    </div>

    {{-- ================= LOANS GROUPED BY STATUS ================= --}}
    <div class="space-y-8">
        @forelse($loans as $status => $group)
            <div class="bg-white shadow rounded-xl p-6">
                <h3 class="font-semibold mb-4 flex items-center justify-between">
                    <span>{{ $status }}</span>
                    <span class="text-sm text-gray-500">
                        {{ $group->count() }} Loans
                    </span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Application</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Outstanding</th>
                                <th class="px-4 py-3 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($group as $l)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-medium">#{{ $l->application_id }}</td>
                                    <td class="px-4 py-3">{{ $l->status }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        R {{ number_format($l->remaining_balance,2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ \Carbon\Carbon::parse($l->created_at)->format('d M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white shadow rounded-xl p-8 text-center text-gray-400">
                No loan activity found.
            </div>
        @endforelse
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const months = @json($trends->pluck('review_month'));
    const approvalRates = @json($trends->pluck('approval_rate'));
    const defaultRates = @json($trends->pluck('default_rate'));

    if(months.length === 0) return;

    var options = {
        chart: {
            type: 'line',
            height: 350,
            toolbar: { show: false }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        series: [
            { name: 'Approval Rate', data: approvalRates },
            { name: 'Default Rate', data: defaultRates }
        ],
        xaxis: {
            categories: months
        },
        yaxis: {
            labels: {
                formatter: function(val) {
                    return val + "%";
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val + "%";
                }
            }
        }
    };

    new ApexCharts(document.querySelector("#trendChart"), options).render();
});
</script>
@endpush

@endsection