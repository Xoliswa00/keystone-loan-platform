<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📋 Loan Applications Review
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                @if($pendingApplications->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No pending loan applications at the moment.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Customer Code</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Loan Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Submitted</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($pendingApplications as $application)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                            {{ $application->user->customer->customer_code ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                            {{ $application->user->name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm capitalize text-gray-700 dark:text-gray-300">
                                            {{ $application->loan_type }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-green-600 dark:text-green-400 font-semibold">
                                            R{{ number_format($application->loan_amount, 2) }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium 
                                                {{ $application->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($application->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                                                @if($application->status === 'pending') ⏳ 
                                                @elseif($application->status === 'approved') ✅ 
                                                @else ❌ 
                                                @endif
                                                <span class="ml-1">{{ ucfirst($application->status) }}</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $application->created_at->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-2 text-sm flex flex-wrap gap-2">
                                            <a href="{{ route('Admin.show', $application->id) }}" 
                                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                                                🔍 View
                                            </a>
                                        
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $pendingApplications->links('pagination::tailwind') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
