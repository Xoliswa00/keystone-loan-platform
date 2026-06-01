<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            💰 Pending Loan Disbursements
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">

          <!--  {{-- Approve All Button --}}
            <div class="flex justify-end mb-4">
                <form method="POST" action="{{ route('disbursements.approveAll') }}">
                    @csrf
                    <button type="submit" 
                        class="px-4 py-2 bg-green-700 text-white rounded hover:bg-green-800 shadow">
                        ✅ Approve All Pending
                    </button>
                </form>
            </div>-->

            {{-- Disbursement Table --}}
            @if($disbursements->isEmpty())
                <p class="text-gray-500 dark:text-gray-400">No loan disbursements found.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Loan ID</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Created</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($disbursements as $disbursement)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                        #{{ $disbursement->loan_id }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                        {{ $disbursement->loan->user->name ?? 'N/A' }} 
                                        <span class="text-xs text-gray-500">({{ $disbursement->loan->user->customer->customer_code ?? 'N/A' }})</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-green-600 dark:text-green-400 font-bold">
                                        R{{ number_format($disbursement->disbursed_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if($disbursement->status === 'waiting_for_approval') bg-yellow-100 text-yellow-800
                                            @elseif($disbursement->Satus === 'approved') bg-green-100 text-green-800
                                            @elseif($disbursement->status === 'released') bg-blue-100 text-blue-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($disbursement->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $disbursement->created_at->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 flex gap-2 text-sm">

                                        {{-- Approve --}}
                                        @if($disbursement->status === 'waiting_for_approval')
                                            <form action="{{ route('disbursements.approve', $disbursement->id) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">
                                                    ✅ Approve
                                                </button>
                                            </form>

                                            {{-- Reject --}}
                                            <form action="{{ route('disbursements.reject', $disbursement->id) }}" method="POST">
                                                @csrf
                                                <input type="text" name="rejection_reason" placeholder="Reason"
                                                       class="border rounded px-2 py-1 text-xs w-32">
                                                <button type="submit"
                                                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">
                                                    ❌ Reject
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Release Funds --}}
                                        @if($disbursement->status === 'approved')
                                            <form action="{{ route('disbursements.release', $disbursement->id) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                                    💸 Release Funds
                                                </button>
                                            </form>
                                        @endif

                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
