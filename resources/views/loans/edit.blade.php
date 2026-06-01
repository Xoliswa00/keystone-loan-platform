<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Loan Application') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- ✅ Error & Success Messages --}}
                @if ($errors->any())
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <strong>There were some issues with your submission:</strong>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- ✅ Edit Form --}}
                <form method="POST" 
                      action="{{ route('loanapplications.update', $loanApplication->id) }}" 
                      enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="loan_type" class="block text-sm font-medium text-gray-700">Loan Type</label>
                            <select name="loan_type" id="loan_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="personal" {{ $loanApplication->loan_type == 'personal' ? 'selected' : '' }}>Personal</option>
                                <option value="home" {{ $loanApplication->loan_type == 'home' ? 'selected' : '' }}>Home</option>
                                <option value="business" {{ $loanApplication->loan_type == 'business' ? 'selected' : '' }}>Business</option>
                            </select>
                        </div>

                        <div>
                            <label for="loan_amount" class="block text-sm font-medium text-gray-700">Loan Amount</label>
                            <input type="number" name="loan_amount" id="loan_amount"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                   value="{{ old('loan_amount', $loanApplication->loan_amount) }}">
                        </div>

                        <div class="md:col-span-2">
                            <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                            <textarea name="purpose" id="purpose" rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('purpose', $loanApplication->purpose) }}</textarea>
                        </div>

                        <div>
                            <label for="collateral" class="block text-sm font-medium text-gray-700">Collateral</label>
                            <input type="text" name="collateral" id="collateral"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                   value="{{ old('collateral', $loanApplication->collateral) }}">
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="pending" {{ $loanApplication->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ $loanApplication->status == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $loanApplication->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label for="approval_date" class="block text-sm font-medium text-gray-700">Approval Date</label>
                            <input type="date" name="approval_date" id="approval_date"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                   value="{{ old('approval_date', $loanApplication->approval_date) }}">
                        </div>

                        <div>
                            <label for="arrears" class="block text-sm font-medium text-gray-700">Has Arrears?</label>
                            <select name="arrears" id="arrears" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="0" {{ $loanApplication->arrears == 0 ? 'selected' : '' }}>No</option>
                                <option value="1" {{ $loanApplication->arrears == 1 ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                    </div>

                    {{-- ✅ File Uploads --}}
                    <div class="mt-8 border-t pt-6">
                        <h3 class="text-lg font-semibold mb-4">Upload Documents</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="bank_statement" class="block text-sm font-medium text-gray-700">Bank Statement</label>
                                <input type="file" name="bank_statement" id="bank_statement"
                                       class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                                @if($loanApplication->bank_statement_path)
                                    <p class="text-sm mt-1">
                                        Current: 
                                        <a href="{{ asset('storage/' . $loanApplication->bank_statement_path) }}" 
                                           class="text-blue-600 underline" target="_blank">View</a>
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label for="payslips" class="block text-sm font-medium text-gray-700">Payslips</label>
                                <input type="file" name="payslips" id="payslips"
                                       class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                                @if($loanApplication->payslip_path)
                                    <p class="text-sm mt-1">
                                        Current: 
                                        <a href="{{ asset('storage/' . $loanApplication->payslip_path) }}" 
                                           class="text-blue-600 underline" target="_blank">View</a>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                            Update Application
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
