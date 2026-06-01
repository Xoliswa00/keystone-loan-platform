@extends('layouts.reports')

@section('report-title', 'Credit Governance Dashboard')

@section('report-content')

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="p-6">




    {{-- ===== ALERT BANNER ===== --}}
    @if($summary->where('risk_flag','AGGRESSIVE')->count() > 0)
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            ⚠️ Aggressive credit behaviour detected. Immediate review recommended.
        </div>
    @endif


    {{-- ===== KPI CARDS ===== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Total Outstanding</p>
            <p class="text-xl font-bold">
                R {{ number_format($totals['total_outstanding'],2) }}
            </p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Total Risk Exposure</p>
            <p class="text-xl font-bold text-red-600">
                R {{ number_format($totals['total_risky'],2) }}
            </p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Average Default Rate</p>
            <p class="text-xl font-bold">
                {{ number_format($totals['avg_default_rate'],2) }}%
            </p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Reviewer Coverage</p>
            <p class="text-xl font-bold">
                {{ number_format($summary->avg('participation_pct'),2) }}%
            </p>
        </div>

    </div>


    {{-- ===== ORGANISATION TREND ===== --}}
    <div class="bg-white shadow rounded p-5 mb-8">

        <h3 class="font-semibold mb-3">
            Organisation Credit Performance Trend
        </h3>

        <div id="trendChart"></div>

    </div>


    {{-- ===== REVIEWER TABLE ===== --}}
    <div class="bg-white shadow rounded overflow-hidden">

        <div class="p-4 border-b">
            <h3 class="font-semibold">
                Reviewer Risk Scorecard
            </h3>
        </div>

        <table class="min-w-full text-sm">

            <thead class="bg-gray-100">
                <tr>
                    <th class="p-3 text-left">Reviewer</th>
                    <th class="p-3">Participation</th>
                    <th class="p-3">Approval %</th>
                    <th class="p-3">Default %</th>
                    <th class="p-3">Outstanding</th>
                    <th class="p-3">Risk Exposure</th>
                    <th class="p-3">Risk Flag</th>
                    <th class="p-3">Profile</th>
                </tr>
            </thead>

            <tbody>
                @foreach($summary as $r)
                <tr class="border-t hover:bg-gray-50">

                    <td class="p-3 font-semibold">
                        {{ $r->reviewer }}
                    </td>

                    <td class="p-3 text-center">
                        {{ $r->participation_pct }}%
                    </td>

                    <td class="p-3 text-center">
                        {{ $r->approval_rate }}%
                    </td>

                    <td class="p-3 text-center">
                        <span class="{{ $r->default_rate > $r->org_default_rate ? 'text-red-600 font-bold' : '' }}">
                            {{ $r->default_rate }}%
                        </span>
                    </td>

                    <td class="p-3 text-right">
                        R {{ number_format($r->outstanding,2) }}
                    </td>

                    <td class="p-3 text-right text-red-600 font-semibold">
                        R {{ number_format($r->risky_exposure,2) }}
                    </td>

                    {{-- ===== RISK COLOUR ===== --}}
                    <td class="p-3 text-center">

                        <span class="
                            px-2 py-1 rounded text-xs font-semibold

                            {{ $r->risk_flag == 'AGGRESSIVE' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $r->risk_flag == 'LOW PARTICIPATION' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $r->risk_flag == 'HIGH DEFAULT' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $r->risk_flag == 'NORMAL' ? 'bg-green-100 text-green-700' : '' }}
                        ">
                            {{ $r->risk_flag }}
                        </span>

                    </td>

                    <td class="p-3 text-center">
                        <a href="{{ route('reports.reviewer', $r->id) }}"
                           class="text-blue-600 hover:underline">
                           View
                        </a>
                    </td>

                </tr>
                @endforeach
            </tbody>

        </table>
    </div>


</div>


{{-- ===== TREND CHART ===== --}}
<script>
document.addEventListener("DOMContentLoaded", function() {

    const months = {!! json_encode($orgTrend->pluck('review_month')) !!};
    const approvalRates = {!! json_encode($orgTrend->pluck('approval_rate')) !!};
    const defaultRates = {!! json_encode($orgTrend->pluck('default_rate')) !!};

    var options = {
        chart: {
            type: 'line',
            height: 340,
            toolbar: { show: false }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        series: [
            {
                name: 'Approval Rate',
                data: approvalRates
            },
            {
                name: 'Default Rate',
                data: defaultRates
            }
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
        }
    };

    var chart = new ApexCharts(document.querySelector("#trendChart"), options);
    chart.render();
});
</script>

@endsection