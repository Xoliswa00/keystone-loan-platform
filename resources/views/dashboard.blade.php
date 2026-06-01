<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-dark-200 leading-tight">
            {{ __('Financial Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden">
                <div class="p-6 sm:p-8 text-gray-900 dark:text-gray-100">
                    <h3 class="text-2xl font-semibold mb-2">Welcome Back, {{ Auth::user()->name }}!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Here's a snapshot of your financial activity and account overview.</p>
                      <div class="flex justify-between items-center mb-6">
                            <a href="{{ route('loanapplications.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700 transition-all">
                                + New Loan Application
                            </a>
                        </div>


                    <!-- Summary Cards Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                        <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md">
                            <h4 class="text-md font-semibold text-blue-600 dark:text-blue-300">Total Outstanding</h4>
                            <p class="text-2xl font-bold mt-2"> R{{ number_format(Auth::user()->customer->current_balance ?? 0, 2) }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Outstanding Balance</p>
                        </div>
                        @php
    $loan = Auth::user()->loanApplications()->where('status', 'approved')->latest()->first();
    $nextPayment = $loan ? $loan->repaymentSchedules()->where('status', 'pending')->orderBy('due_date')->first() : null;
@endphp



                        <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md">
                            <h4 class="text-md font-semibold text-green-600 dark:text-green-300">Latest Loan</h4>
                            <p class="text-2xl font-bold mt-2">    R{{ number_format($nextPayment->emi_amount ?? 0, 2) }}
</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Available Funds</p>
                        </div>

                        <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md">
                            <h4 class="text-md font-semibold text-yellow-600 dark:text-yellow-300">Next Payment</h4>
                            <p class="text-2xl font-bold mt-2">{{ $nextPayment ? $nextPayment->due_date: 'N/A' }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Scheduled Date</p>
                        </div>

                        <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-md">
                            <h4 class="text-md font-semibold text-red-600 dark:text-red-300">User Status</h4>
                            <p class="text-2xl font-bold mt-2">{{ Auth::user()->status}}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">User Status</p>
                        </div>
                    </div>
                    
                    

             <div class="mt-10">
    <h4 class="text-md font-semibold mb-2">Your Financial Progress</h4>

    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg h-60 
                flex flex-col items-center justify-center 
                text-gray-500 dark:text-gray-400 text-sm">
        <p class="font-medium">Repayment Progress</p>
        <p class="text-xs mt-1">
            Payments made vs remaining balance
        </p>
    </div>
</div>


@php
    $userName = auth()->user()->name ?? 'Customer';
    $userId = auth()->user()->customer->customer_code ?? ''; // Or customer number if you store separately
    $businessWhatsApp = '27721853349'; // Replace with your business WhatsApp
    $supportWhatsApp = '27674017419'; // Support WhatsApp number
@endphp

<!-- Navigation Links -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-10">

    <!-- Profile -->
    <a href="{{ route('profile.edit') }}" class="bg-blue-100 dark:bg-blue-900 p-4 rounded-lg shadow hover:shadow-md transition">
        <h4 class="font-semibold text-blue-700 dark:text-blue-200">Profile</h4>
        <p class="text-xs text-gray-600 dark:text-gray-300">Update personal details</p>
    </a>

    <!-- Messages via Business WhatsApp -->
    @php
        $msgBusiness = urlencode("Hi, I am $userName (Customer ID: $userId). I want to chat about your services.");
    @endphp
    <a href="https://wa.me/{{ $businessWhatsApp }}?text={{ $msgBusiness }}" 
       target="_blank" 
       class="bg-green-100 dark:bg-green-900 p-4 rounded-lg shadow hover:shadow-md transition">
        <h4 class="font-semibold text-green-700 dark:text-green-200">Messages</h4>
        <p class="text-xs text-gray-600 dark:text-gray-300">Chat with us on WhatsApp</p>
    </a>

    <!-- Statements (Coming Soon) -->
    <a href="#" class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded-lg shadow hover:shadow-md transition cursor-not-allowed">
        <h4 class="font-semibold text-yellow-700 dark:text-yellow-200">Statements</h4>
        <p class="text-xs text-gray-600 dark:text-gray-300">Feature coming soon</p>
    </a>

    <!-- Support via WhatsApp -->
    @php
        $msgSupport = urlencode("Hi, I am $userName (Customer ID: $userId). I need support.");
    @endphp
    <a href="https://wa.me/{{ $supportWhatsApp }}?text={{ $msgSupport }}" 
       target="_blank" 
       class="bg-red-100 dark:bg-red-900 p-4 rounded-lg shadow hover:shadow-md transition">
        <h4 class="font-semibold text-red-700 dark:text-red-200">Support</h4>
        <p class="text-xs text-gray-600 dark:text-gray-300">Chat with support via WhatsApp</p>
    </a>
</div>



                    <!-- Tips / Updates Section -->
                    <div class="mt-8 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                        <h5 class="text-md font-medium text-gray-700 dark:text-gray-100">Pro Tip</h5>
                        <p class="text-xs text-gray-600 dark:text-gray-300">Set up auto-debit to avoid late fees and improve your credit profile.</p>
                    </div>

                  

                    <!-- Footer -->
                    <footer class="mt-12 text-center text-xs text-gray-500 dark:text-gray-400">
                        &copy; {{ date('Y') }} Liger Management. All rights reserved.
                    </footer>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
