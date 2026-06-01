<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Loan Application Details') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-md rounded-lg p-6">

                {{-- SYSTEM ALERTS --}}
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-md bg-green-100 border border-green-300 text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 rounded-md bg-red-100 border border-red-300 text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-md bg-red-100 border border-red-300 text-red-800">
                        <strong class="block mb-2">Please fix the following:</strong>
                        <ul class="list-disc ml-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif


                {{-- APPLICATION SUMMARY --}}
                <h3 class="text-2xl font-bold mb-6 text-gray-700">
                    Application Summary
                </h3>

                @php
                    $statusColors = [
                        'approved' => 'bg-green-200 text-green-800',
                        'pending' => 'bg-yellow-200 text-yellow-800',
                        'rejected' => 'bg-red-200 text-red-800',
                        'under_review' => 'bg-blue-200 text-blue-800',
                    ];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">

                    <p><strong>User ID:</strong> {{ $loanApplication->user_id }}</p>

                    <p><strong>Loan Type:</strong> {{ $loanApplication->loan_type }}</p>

                    <p>
                        <strong>Loan Amount:</strong>
                        R{{ number_format($loanApplication->loan_amount, 2) }}
                    </p>

                    <p><strong>Purpose:</strong> {{ $loanApplication->purpose }}</p>

                    <p><strong>Collateral:</strong> {{ $loanApplication->collateral }}</p>

                    <p>
                        <strong>Status:</strong>

                        <span class="px-3 py-1 rounded text-sm font-medium
                        {{ $statusColors[$loanApplication->status] ?? 'bg-gray-200 text-gray-800' }}">
                            {{ ucfirst(str_replace('_',' ',$loanApplication->status)) }}
                        </span>
                    </p>

                    <p>
                        <strong>Approval Date:</strong>
                        {{ $loanApplication->approval_date ?? 'N/A' }}
                    </p>

                    <p>
                        <strong>Created At:</strong>
                        {{ $loanApplication->created_at->format('Y-m-d H:i') }}
                    </p>

                </div>


                {{-- ACTION BUTTONS --}}
                <div class="mt-8 flex items-center gap-4">

                <!--    {{-- DELETE --}}
                    <form action="{{ route('loanapplications.destroy', $loanApplication->id) }}"
                          method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this application?')">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                            class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                            Delete
                        </button>
                    </form>   -->

                </div>


                {{-- DOCUMENT UPLOAD --}}
                <div class="mt-10 border-t pt-6">

                    <h3 class="text-xl font-semibold text-gray-700 mb-4">
                        Upload Supporting Documents
                    </h3>

                    <form action="{{ route('loanapplications.update', $loanApplication->id) }}"
                          method="POST"
                          enctype="multipart/form-data">

                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Bank Statement
                                </label>

                                <input type="file"
                                       name="bank_statement"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm
                                       focus:ring-indigo-500 focus:border-indigo-500">
                            </div>


                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Payslip
                                </label>

                                <input type="file"
                                       name="payslip"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm
                                       focus:ring-indigo-500 focus:border-indigo-500">
                            </div>


                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    ID Copy
                                </label>

                                <input type="file"
                                       name="id_copy"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm
                                       focus:ring-indigo-500 focus:border-indigo-500">
                            </div>

                        </div>

                        <div class="mt-6">
                            <button type="submit"
                                class="bg-blue-600 text-white px-5 py-2 rounded-md hover:bg-blue-700 shadow">
                                Upload Documents
                            </button>
                        </div>

                    </form>


                    {{-- DOCUMENT LIST --}}
                    @if($loanApplication->documents && $loanApplication->documents->count())

                        <div class="mt-8">

                            <h4 class="text-md font-semibold mb-3">
                                Uploaded Documents
                            </h4>

                            <div class="space-y-3">

                                @foreach ($loanApplication->documents as $doc)

                                    <div class="flex items-center justify-between
                                                border rounded-md p-3 bg-gray-50">

                                        <div>
                                            <span class="font-medium">
                                                {{ ucfirst($doc->document_type) }}
                                            </span>

                                            <span class="text-sm text-gray-500 ml-2">
                                                {{ $doc->created_at->diffForHumans() }}
                                            </span>
                                        </div>

                                        <a href="{{ asset('storage/' . $doc->path) }}"
                                           target="_blank"
                                           class="text-blue-600 hover:underline text-sm">
                                           View
                                        </a>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @endif

                </div>

            </div>

        </div>
    </div>
</x-app-layout>

