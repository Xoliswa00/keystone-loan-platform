<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Loan Applications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
   <div class="flex justify-between items-center">
                            <h1 class="text-3xl font-bold">Loan Applications</h1>
                            <a href="{{ route('loanapplications.create') }}" class="bg-blue-500 text-white px-5 py-2 rounded-md hover:bg-blue-600 transition duration-200">
                                Add New Application
                            </a>
                        </div>           <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container mx-auto mt-8">
                      

                        @if($Applications->isEmpty())
                            <p class="mt-6 text-gray-600 dark:text-gray-400 text-center">
                                No loan applications found.
                            </p>
                        @else
                            <div class="overflow-x-auto mt-6">
                                <table class="w-full bg-white dark:bg-gray-700 border border-gray-300 rounded-lg shadow-lg">
                                    <thead>
                                        <tr class="bg-gray-200 dark:bg-gray-900 text-gray-800 dark:text-gray-200 uppercase text-lg font-semibold">
                                            <th class="px-6 py-4 text-left">Loan Type</th>
                                            <th class="px-6 py-4 text-left">Amount</th>
                                            <th class="px-6 py-4 text-left">Status</th>
                                            <th class="px-6 py-4 text-left">Applicant</th>
                                            <th class="px-6 py-4 text-left">Created At</th>
                                            <th class="px-6 py-4 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-800 dark:text-gray-100 text-sm font-light text-center">
                                        @foreach ($Applications as $application)
                                            <tr class="border-b hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-200">
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $application->loan_type }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($application->loan_amount, 2) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ ucfirst($application->status) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $application->user->name ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $application->created_at->format('Y-m-d') }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <div class="flex justify-center items-center space-x-2">
                                                        <a href="{{ route('loanapplications.show', $application->id) }}" class="text-blue-500 hover:underline" aria-label="View Application">View</a>
                                                        <span>|</span>
                                                        <a href="{{ route('loanapplications.edit', $application->id) }}" class="text-yellow-500 hover:underline" aria-label="Edit Application">Edit</a>
                                                        <span>|</span>
                                                   
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Links -->
                            <div class="mt-6">
                                {{ $Applications->links('pagination::tailwind') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
