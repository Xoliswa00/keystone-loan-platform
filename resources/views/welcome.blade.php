@section('title', 'Welcome to Liger Holding')

<x-guest-layout>
    <!-- Hero Section -->
    <div class="py-10 px-4 sm:py-16 sm:px-6 lg:py-24 lg:px-8 text-center bg-gradient-to-b from-gray-100 to-gray-300 dark:from-gray-900 dark:to-gray-800">

        {{-- Logo --}}
        <div class="mb-2">
            <a href="/">
                <x-application-logo />
            </a>
        </div>

        {{-- Headline --}}
        <h1 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white leading-tight mb-4" data-aos="fade-up">
            Welcome to <span class="text-blue-600 dark:text-blue-400">Liger Holding</span>
        </h1>

        {{-- Tagline --}}
        <p class="text-base sm:text-lg lg:text-xl text-gray-700 dark:text-gray-300 max-w-3xl mx-auto mb-10 px-2" data-aos="fade-up" data-aos-delay="150">
            Secure, smart, and scalable solutions to manage your loans, clients, and repayments — all from one intuitive platform.
        </p>

        {{-- CTA Buttons --}}
        <div class="flex flex-col sm:flex-row justify-center gap-4 text-base sm:text-lg font-semibold px-2" data-aos="fade-up" data-aos-delay="300">
            <a href="{{ route('login') }}"
               class="px-6 py-3 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition w-full sm:w-auto">
                Sign In
            </a>
            <a href="{{ route('register') }}"
               class="px-6 py-3 bg-white border border-blue-600 text-blue-600 rounded-lg shadow hover:bg-blue-100 transition w-full sm:w-auto dark:bg-gray-900 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-gray-800">
                Create Account
            </a>
        </div>
    </div>

    <!-- Feature Highlights -->
    <section class="bg-white dark:bg-gray-900 py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white mb-4">
                Why Choose Liger Holding
            </h2>
            <p class="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Streamline your loan management process with an all-in-one platform designed to save time and increase efficiency.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8" data-aos="fade-up" data-aos-delay="100">
            <div class="bg-gray-100 dark:bg-gray-800 p-6 rounded-lg shadow-lg hover:scale-105 transition">
                <h3 class="text-xl font-semibold text-blue-600 mb-2">Comprehensive Client Profiles</h3>
                <p class="text-gray-700 dark:text-gray-300">Capture and manage all borrower information easily for faster decision-making.</p>
            </div>
            <div class="bg-gray-100 dark:bg-gray-800 p-6 rounded-lg shadow-lg hover:scale-105 transition">
                <h3 class="text-xl font-semibold text-blue-600 mb-2">Flexible Loan Management</h3>
                <p class="text-gray-700 dark:text-gray-300">Set up interest rates, repayment schedules, and track disbursements with precision.</p>
            </div>
            <div class="bg-gray-100 dark:bg-gray-800 p-6 rounded-lg shadow-lg hover:scale-105 transition">
                <h3 class="text-xl font-semibold text-blue-600 mb-2">Automated Reminders</h3>
                <p class="text-gray-700 dark:text-gray-300">Keep clients on track with automated notifications for payments and deadlines.</p>
            </div>
        </div>
    </section>

    {{-- Footer Line --}}
    <div class="text-center mt-10 text-gray-500 dark:text-gray-400 italic text-sm px-4">
        “Smarter lending starts with Liger Holding.”
    </div>
</x-guest-layout>
