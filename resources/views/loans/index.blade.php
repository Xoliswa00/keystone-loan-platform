<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-blue-600 dark:text-blue-800 leading-tight">
            {{ __('Loans Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container mx-auto">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl sm:text-3xl font-semibold">Loan Overview</h1>
                            <a href="{{ route('loanapplications.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700 transition-all">
                                + New Loan Application
                            </a>
                        </div>

                        <!-- Loan Summary Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-lg shadow-md">
                                <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Total Applications</h4>
                                <p class="text-2xl font-bold mt-2">{{ $loansapp->count() }}</p>
                            </div>
                            <div class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded-lg shadow-md">
                                <h4 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200">Pending Applications</h4>
                                <p class="text-2xl font-bold mt-2">{{ $loansapp->where('status', 'pending')->count() }}</p>
                            </div>
                            <div class="bg-green-100 dark:bg-green-900 p-4 rounded-lg shadow-md">
                                <h4 class="text-lg font-semibold text-green-800 dark:text-green-200">Approved Loans</h4>
                                <p class="text-2xl font-bold mt-2">{{ $loansapp->where('status', 'approved')->count() }}</p>
                            </div>
                            <div class="bg-red-100 dark:bg-red-900 p-4 rounded-lg shadow-md">
                                <h4 class="text-lg font-semibold text-red-800 dark:text-red-200">Rejected Loans</h4>
                                <p class="text-2xl font-bold mt-2">{{ $loansapp->where('status', 'rejected')->count() }}</p>
                            </div>
                        </div>

                        @if($loansapp->isEmpty())
                            <p class="mt-4 text-gray-600 dark:text-gray-400">No loan records found. Start by creating a new loan application.</p>
                        @else
                            <!-- Loan Table -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-auto border-collapse border border-gray-300 dark:border-gray-700 text-sm">
                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th class="border px-4 py-2">Loan ID</th>
                                            <th class="border px-4 py-2">Type</th>
                                            <th class="border px-4 py-2">Amount</th>
                                            <th class="border px-4 py-2">Total Due</th>
                                            <th class="border px-4 py-2">Status</th>
                                            <th class="border px-4 py-2">Applicant</th>
                                            <th class="border px-4 py-2">Created</th>
                                            <th class="border px-4 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($loansapp as $loan)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="border px-4 py-2">{{ $loan->id }}</td>
                                                <td class="border px-4 py-2">{{ $loan->loan_type }}</td>
                                                <td class="border px-4 py-2">R{{ number_format($loan->loan_amount, 2) }}
                                                 <th class="border px-4 py-2">{{$loan->loanfee->total_due}}   
                                                </td>
                                                <td class="border px-4 py-2 capitalize">{{ $loan->status }}</td>
                                                <td class="border px-4 py-2">{{ $loan->user->name ?? 'N/A' }}</td>
                                                <td class="border px-4 py-2">{{ $loan->created_at->format('d M Y') }}</td>
                                                <td class="border px-4 py-2">
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('loans.show', $loan->id) }}" class="text-blue-500 hover:underline" aria-label="View Loan">View</a>
                                               <!--         <a href="{{ route('loans.edit', $loan->id) }}" class="text-yellow-500 hover:underline" aria-label="Edit Loan">Edit</a> --> 
                                               
                                               @if($loan->status=='Pending')
                                                        <form action="{{ route('loanapplications.destroy', $loan->id) }}" method="POST" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-500 hover:underline" aria-label="Delete Loan">Delete</button>
                                                        </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="mt-6">
                                {{ $loansapp->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
