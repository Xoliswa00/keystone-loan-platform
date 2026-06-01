<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Repayment Schedules') }}
        </h2>
    </x-slot>

    @php
        $totalScheduled   = $loanRepayments->sum('emi_amount');
        $totalPaid        = $loanRepayments->where('status', 'paid')->sum('emi_amount');
        $totalOutstanding = $loanRepayments
                                ->whereIn('status', ['pending', 'overdue'])
                                ->sum('emi_amount');
    @endphp

    <div class="py-10 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Success Message --}}
        @if (session('success'))
            <div class="mb-6 rounded-md bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Summary Totals --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-sm text-gray-500">Total Scheduled</p>
                <p class="text-2xl font-semibold text-gray-800">
                    R {{ number_format($totalScheduled, 2) }}
                </p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-sm text-gray-500">Total Paid</p>
                <p class="text-2xl font-semibold text-green-600">
                    R {{ number_format($totalPaid, 2) }}
                </p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-sm text-gray-500">Outstanding Balance</p>
                <p class="text-2xl font-semibold text-red-600">
                    R {{ number_format($totalOutstanding, 2) }}
                </p>
            </div>
        </div>
        
        @php
    $monthlyOutstanding = $loanRepayments
        ->filter(fn ($r) => in_array($r->status, ['pending', 'overdue']))
        ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->due_date)->format('F Y'))
        ->map(fn ($group) => $group->sum('emi_amount'));
@endphp

        {{-- Monthly Outstanding Summary --}}
@if ($monthlyOutstanding->isNotEmpty())
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                Monthly Outstanding Summary
            </h3>
            <p class="text-sm text-gray-500">
                Outstanding repayment exposure by due month
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            Month
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">
                            Outstanding Amount
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            Risk Level
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($monthlyOutstanding as $month => $amount)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-sm text-gray-800">
                                {{ $month }}
                            </td>

                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">
                                R {{ number_format($amount, 2) }}
                            </td>

                            <td class="px-6 py-4 text-sm">
                                @php
                                    $riskClass = $amount > 0
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-green-100 text-green-800';
                                @endphp

                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $riskClass }}">
                                    Outstanding
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="bg-gray-100">
                    <tr>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-700">
                            Total Outstanding
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-bold text-gray-900">
                            R {{ number_format($monthlyOutstanding->sum(), 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif


        {{-- Action Bar --}}
        <div class="flex justify-between items-center mb-6">
            <p class="text-gray-600">
                Manage loan repayment schedules and track payment status.
            </p>
        </div>
        @php
    $groupedRepayments = $loanRepayments
        ->whereIn('status', ['pending', 'overdue'])
        ->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->due_date)->format('F Y');
        });
@endphp
@php
    $groupedByMonthAndCustomer = $groupedRepayments->map(function ($monthGroup) {
        return $monthGroup->groupBy(function ($item) {
            return $item->loan->customer->customer_code 
                ?? $item->loan->customer->id 
                ?? 'Unknown Customer';
        });
    });
@endphp



        {{-- Table --}}
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            @foreach ($groupedByMonthAndCustomer as $month => $customers)
    @php
        $monthTotal = $customers->flatten()->sum('emi_amount');
    @endphp

    {{-- Month Header --}}
    <div class="bg-gray-100 px-6 py-4 font-semibold text-gray-800 rounded-t-lg mt-8">
        {{ $month }} — Outstanding: R {{ number_format($monthTotal, 2) }}
    </div>

    @foreach ($customers as $customerName => $schedules)
        @php
            $customerTotal = $schedules->sum('emi_amount');
        @endphp

        {{-- Customer Header --}}
        <div class="bg-white border-x border-b px-6 py-3">
            <p class="text-sm font-medium text-gray-700">
                Customer: {{ $customerName }}
            </p>
            <p class="text-sm text-red-600 font-semibold">
                Outstanding: R {{ number_format($customerTotal, 2) }}
            </p>
        </div>

        {{-- Detail Table --}}
        <div class="bg-white border-x border-b overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Loan</th>
                        <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-2 text-sm">
                                {{ $schedule->loan->loan_reference ?? $schedule->loan->id }}
                            </td>
                            <td class="px-6 py-2 text-sm">
                                {{ \Carbon\Carbon::parse($schedule->due_date)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-2 text-sm text-right font-medium">
                                R {{ number_format($schedule->emi_amount, 2) }}
                            </td>
                            <td class="px-6 py-2 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $statusColors[$schedule->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endforeach




        </div>
    </div>
</x-app-layout>
