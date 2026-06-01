<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            General Ledger – Account Summary
        </h2>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8">

        {{-- Filters --}}
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="input">
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="input">
            <button class="btn btn-primary">Apply</button>
        </form>

        {{-- Summary Table --}}
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left">Account</th>
                        <th class="px-4 py-2 text-left">Type</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2 text-right">Closing Balance</th>
                        <th class="px-4 py-2 text-center">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @foreach($accounts as $acc)
                        @php
                            $balance = $acc->normal_balance === 'debit'
                                ? $acc->total_debit - $acc->total_credit
                                : $acc->total_credit - $acc->total_debit;
                        @endphp

                        <tr>
                            <td class="px-4 py-2 font-medium">
                                {{ $acc->code }} – {{ $acc->name }}
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $acc->type }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ number_format($acc->total_debit,2) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ number_format($acc->total_credit,2) }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold">
                                {{ number_format($balance,2) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                <a href="{{ route('gl.detail', ['account' => $acc->id]) }}"
                                   class="text-blue-600 hover:underline">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
