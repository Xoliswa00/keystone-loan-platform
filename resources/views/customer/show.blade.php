<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Customer Profile - {{ $customer->customer_code }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">{{ session('error') }}</div>
        @endif

        {{-- CUSTOMER SUMMARY CARDS --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total Loans</p>
                <p class="text-xl font-bold text-gray-800">{{ $customer->loanApplications->count() }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total Paid</p>
                <p class="text-xl font-bold text-green-600">
                    R {{ number_format($customer->loanApplications->sum(fn($l) => $l->repaymentSchedules->where('status','paid')->sum('emi_amount')),2) }}
                </p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Outstanding</p>
                <p class="text-xl font-bold text-red-600">
                    R {{ number_format($customer->loanApplications->sum(fn($l) => $l->repaymentSchedules->whereIn('status',['pending','overdue','Failed'])->sum('emi_amount')),2) }}
                </p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Next Due Payment</p>
                <p class="text-xl font-bold text-gray-800">
                    @php
                        $nextPayment = $customer->loanApplications->flatMap->repaymentSchedules
                            ->whereIn('status',['pending','overdue'])
                            ->sortBy('due_date')
                            ->first();
                    @endphp
                    {{ $nextPayment ? \Carbon\Carbon::parse($nextPayment->due_date)->format('d M Y') : 'N/A' }}
                </p>
            </div>
        </div>

        {{-- PERSONAL DETAILS --}}
        <div class="bg-white shadow-md rounded p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Personal Details</h3>
            <p><strong>Name:</strong> {{ $customer->user->name }}</p>
            <p><strong>Email:</strong> {{ $customer->user->email }}</p>
            <p><strong>Phone:</strong> {{ $customer->user->phone }}</p>
            <p><strong>Address:</strong> {{ $customer->user->address ?? 'N/A' }}</p>
            <p><strong>Current Balance:</strong> R{{ number_format($customer->current_balance, 2) }}</p>
        </div>

        {{-- LOANS AND PAYMENTS --}}
        <div class="bg-white shadow-md rounded p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Loans</h3>

            @forelse($customer->loanApplications as $loan)
                <div class="border rounded-lg p-4 mb-4 bg-gray-50">
                    {{-- Loan Header --}}
                    <div class="flex justify-between items-start">
                        <div>
                            <p><strong>Loan Type:</strong> {{ ucfirst($loan->loan_type) }}</p>
                            <p><strong>Amount:</strong> R{{ number_format($loan->loan_amount, 2) }}</p>
                            <p><strong>Status:</strong> 
                                <span class="px-2 py-1 rounded text-white 
                                    {{ $loan->status === 'approved' ? 'bg-green-600' : ($loan->status === 'pending' ? 'bg-yellow-500' : 'bg-red-600') }}">
                                    {{ ucfirst($loan->status) }}
                                </span>
                            </p>
                            <p><strong>Approval Date:</strong> {{ $loan->approval_date ?? 'Pending' }}</p>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('loanapplications.show', $loan->id) }}" class="text-blue-600 hover:underline">View Loan</a>
                        </div>
                        
                         <a href="{{ route('Admin.show', $loan->id) }}" 
                                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                                                🔍 View history
                                            </a>
                    </div>

                    {{-- PAYMENTS (GROUPED BY MONTH) --}}
                    @php
                        $paymentsByMonth = $loan->repaymentSchedules
                            ->whereIn('status',['pending','overdue','paid'])
                            ->groupBy(fn($p) => \Carbon\Carbon::parse($p->due_date)->format('F Y'));
                    @endphp

                    @foreach($paymentsByMonth as $month => $payments)
                        <div class="mt-4 bg-white border rounded p-3">
                            <p class="font-semibold">{{ $month }} — Total: R {{ number_format($payments->sum('emi_amount'),2) }}</p>
                            <table class="min-w-full text-sm mt-2 border">
                                <thead>
                                    <tr class="bg-gray-200">
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Status</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $payment)
                                        <tr class="border-t">
                                            <td class="px-3 py-2">{{ \Carbon\Carbon::parse($payment->due_date)->format('d M Y') }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-1 rounded text-white
                                                    {{ $payment->status === 'paid' ? 'bg-green-600' : ($payment->status === 'pending' ? 'bg-yellow-500' : 'bg-red-600') }}">
                                                    {{ ucfirst($payment->status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">R{{ number_format($payment->emi_amount,2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    {{-- DOCUMENTS --}}
                    <div class="mt-4">
                        <h4 class="font-semibold"></h4>
                 
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm">No loans found for this customer.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
