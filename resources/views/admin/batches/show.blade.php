<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            NuPay Import Batch: {{ $batch->import_ref }}
        </h2>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Post batch button --}}
        <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
            <form method="POST" action="{{ route('nu-pay.import.post', $batch->import_ref) }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Post Batch</button>
            </form>
        </div>

        {{-- Summary by action date --}}
<div class="bg-white shadow-sm rounded-lg p-4 mb-4">
    <h3 class="font-semibold mb-3">Monthly Summary (By Transaction Type)</h3>

    <table class="table-auto w-full border-collapse border border-gray-200 text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="border px-3 py-2">Month</th>
                <th class="border px-3 py-2">Transaction Type</th>
                <th class="border px-3 py-2 text-right">Total Amount</th>
                <th class="border px-3 py-2 text-right">NuPay Fee (2%)</th>
                <th class="border px-3 py-2 text-right">Net Amount</th>
                <th class="border px-3 py-2 text-center">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary as $month => $types)
                @foreach($types as $type => $totals)
                    <tr>
                        <td class="border px-3 py-2">
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                        </td>
                        <td class="border px-3 py-2 font-medium capitalize">
                            {{ str_replace('_', ' ', $type) }}
                        </td>
                        <td class="border px-3 py-2 text-right">
                            {{ number_format($totals['total_amount'], 2) }}
                        </td>
                        <td class="border px-3 py-2 text-right">
                            {{ number_format($totals['total_fee'], 2) }}
                        </td>
                        <td class="border px-3 py-2 text-right">
                            {{ number_format($totals['net_amount'], 2) }}
                        </td>
                        <td class="border px-3 py-2 text-center">
                            {{ $totals['count'] }}
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>



        {{-- Transactions table --}}
        <div class="bg-white shadow-sm rounded-lg p-4">
            <table class="table-auto w-full border-collapse border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2">ID</th>
                        <th class="border px-4 py-2">Debtor</th>
                        <th class="border px-4 py-2">Amount</th>
                        <th class="border px-4 py-2">Status</th>
                        <th class="border px-4 py-2">Posted At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $txn)
                        <tr>
                            <td class="border px-4 py-2">{{ $txn->id }}</td>
                            <td class="border px-4 py-2">{{ $txn->debtor_name }}</td>
                            <td class="border px-4 py-2">{{ number_format($txn->instalment_amount, 2) }}</td>
                            <td class="border px-4 py-2">{{ $txn->status }}</td>
                            <td class="border px-4 py-2">{{ $txn->posted_at ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
