<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Account Detail') }}
        </h2>
    </x-slot>

    <div class="container mx-auto py-6 px-4">

        <!-- Center the form and limit its width -->
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8  bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <form action="{{ route('accountdetails.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="account_holder_name" class="block text-sm font-medium text-gray-700">Account Holder Name</label>
                    <input type="text" name="account_holder_name" id="account_holder_name" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" value="{{ old('account_holder_name') }}" required>
                    @error('account_holder_name')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name</label>
                    <input type="text" name="bank_name" id="bank_name" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" value="{{ old('bank_name') }}" required>
                    @error('bank_name')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700">Account Number</label>
                    <input type="text" name="account_number" id="account_number" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" value="{{ old('account_number') }}" required>
                    @error('account_number')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="account_type" class="block text-sm font-medium text-gray-700">Account Type</label>
                    <select name="account_type" id="account_type" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" required>
                        <option value="savings" {{ old('account_type') == 'savings' ? 'selected' : '' }}>Savings</option>
                        <option value="current" {{ old('account_type') == 'current' ? 'selected' : '' }}>Check</option>
                    </select>
                    @error('account_type')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" required>
                        <option value="debit_order" {{ old('payment_method') == 'debit_order' ? 'selected' : '' }}>Debit Order</option>
                       
                    </select>
                    @error('payment_method')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="form-control border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full" required>
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full">
                        Create Account Detail
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
