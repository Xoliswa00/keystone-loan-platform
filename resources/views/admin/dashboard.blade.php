<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Admin Dashboard
        </h2>
    </x-slot>

    <div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

        {{-- Welcome --}}
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">
            <h3 class="text-2xl font-semibold mb-2">Welcome Back, {{ Auth::user()->name }}!</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Overview of system metrics and quick actions.
            </p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Pending Loans --}}
            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-md font-semibold text-indigo-600 dark:text-indigo-300">Pending Loans</h4>
                        <p class="text-2xl font-bold mt-2">{{ $pendingLoansCount ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Applications awaiting review</p>
                    </div>
                    <div>
                        <svg class="w-8 h-8 text-indigo-500 dark:text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h6v6m2 0H7a2 2 0 01-2-2V7a2 2 0 012-2h6l2 2h2a2 2 0 012 2v6a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <a href="{{ route('admin.loans') }}" class="mt-4 text-sm text-indigo-700 dark:text-indigo-200 hover:underline">Review Loans →</a>
            </div>

            {{-- Pending Payments --}}
            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-md font-semibold text-yellow-600 dark:text-yellow-300">Pending Payments</h4>
                        <p class="text-2xl font-bold mt-2">{{ $totalLoansDisbursed ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Payments waiting approval</p>
                    </div>
                    <div>
                        <svg class="w-8 h-8 text-yellow-500 dark:text-yellow-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.105 0-2 .895-2 2s.895 2 2 2 2-.895 2-2-.895-2-2-2zm0-6v2m0 16v2m10-10h-2M4 12H2m16.364 6.364l-1.414-1.414M6.05 6.05L4.636 4.636m0 12.728l1.414-1.414M16.95 7.05l1.414-1.414"/>
                        </svg>
                    </div>
                </div>
                <a href="{{ route('Admin.Disbursement') }}" class="mt-4 text-sm text-yellow-700 dark:text-yellow-200 hover:underline">Review Payments →</a>
            </div>

            {{-- Repayments Outstanding --}}
            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md flex flex-col justify-between">
                <h4 class="text-md font-semibold text-red-600 dark:text-red-300">Repayments Outstanding</h4>
                <p class="text-2xl font-bold mt-2">R{{ number_format($todaysRepayments ?? 0, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Expected but unpaid</p>
            </div>

            {{-- Total Customers --}}
            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md flex flex-col justify-between">
                <h4 class="text-md font-semibold text-green-600 dark:text-green-300">Total Customers</h4>
                <p class="text-2xl font-bold mt-2">{{ $customerCount ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Active users in the system</p>
            </div>
        </div>

        {{-- Alerts --}}
        @if($overdueLoansCount > 0)
        <div class="mt-6 p-4 bg-red-100 dark:bg-red-800 text-red-800 dark:text-red-100 rounded-lg">
            ⚠️ You have {{ $overdueLoansCount }} overdue loans. <a href="#" class="underline">View now</a>
        </div>
        @endif

        {{-- Recent Activity --}}
        <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mt-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent Loan Applications</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 text-left">ID</th>
                            <th class="px-4 py-2 text-left">Customer</th>
                            <th class="px-4 py-2 text-left">Amount</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
                        @foreach($recentApplications as $app)
                        <tr>
                            <td class="px-4 py-2">#{{ $app->id }}</td>
                            <td class="px-4 py-2">{{ $app->user->name }}</td>
                            <td class="px-4 py-2">R{{ number_format($app->loan_amount, 2) }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs font-medium 
                                {{ $app->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($app->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($app->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ $app->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Admin Tip --}}
        <div class="mt-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
            <h5 class="text-md font-medium text-gray-700 dark:text-gray-100">Admin Tip</h5>
            <p class="text-xs text-gray-600 dark:text-gray-300">
                Review loan approvals daily and monitor overdue accounts for quick intervention.
            </p>
        </div>

        {{-- Footer --}}
        <footer class="mt-12 text-center text-xs text-gray-500 dark:text-gray-400">
            &copy; {{ date('Y') }} Liger Admin Panel. All rights reserved.
        </footer>

    </div>
</x-app-layout>
