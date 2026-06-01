<x-guest-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-dark-200 leading-tight">
            {{ __('Registration') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen flex flex-col items-center px-4 lg:px-12">
        <div class="w-full max-w-md sm:max-w-3xl lg:max-w-5xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">

            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-10 grid grid-cols-1 lg:grid-cols-2 gap-10">
                    @csrf

                    <!-- Personal Information Section -->
                    <section class="space-y-6">
                        <h2 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-4
                            text-base sm:text-1xl">
                            Personal Information
                            @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


                        </h2>

                        <!-- Name -->
                        <div class="space-y-2">
                            <x-input-label for="name" :value="__('Full Name')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="name"
                                type="text"
                                name="name"
                                class="w-full form-control px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="John Doe"
                                required
                                aria-describedby="nameHelp"
                            />
                            <x-input-error :messages="$errors->get('name')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- ID Number -->
                        <div class="space-y-2">
                            <x-input-label for="ID_Number" :value="__('ID Number')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="ID_Number"
                                type="text"
                                name="ID_Number"
                                class="w-full form-control px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="001212218608"
                                required
                                aria-describedby="nameHelp"
                            />
                            <x-input-error :messages="$errors->get('ID_Number')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-2 mt-3">
                            <x-input-label for="email" :value="__('Email Address')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="email"
                                type="email"
                                name="email"
                                class="w-full form-control px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="name@example.com"
                                required
                                aria-describedby="emailHelp"
                            />
                            <x-input-error :messages="$errors->get('email')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>
                    </section>

                    <!-- Contact Information -->
                    <section class="space-y-6">
                        <h2 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-4
                            text-base sm:text-2xl">
                            Contact Information
                        </h2>

                        <!-- Phone -->
                        <div class="space-y-2">
                            <x-input-label for="phone" :value="__('Phone Number')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="phone"
                                type="text"
                                name="phone"
                                class="w-full form-control px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="+1234567890"
                                required
                                aria-describedby="phoneHelp"
                            />
                            <x-input-error :messages="$errors->get('phone')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Address -->
                        <div class="space-y-2 mt-3">
                            <x-input-label for="address" :value="__('Address')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="address"
                                type="text"
                                name="address"
                                class="w-full form-control px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="123 Main Street"                                 required

                                aria-describedby="addressHelp"
                            />
                            <x-input-error :messages="$errors->get('address')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>
                    </section>

                    <!-- Employment Details -->
                    <section class="space-y-6">
                        <h2 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-4
                            text-base sm:text-2xl">
                            Employment Details
                        </h2>

                        <!-- Employment Status -->
                        <div class="space-y-2">
                            <x-input-label for="employment_status" :value="__('Employment Status')" class="text-base sm:text-lg" />
                            <select
                                id="employment_status"
                                name="employment_status"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"                                 required

                            >
                                <option value="" disabled selected>Select Employment Status</option>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Self-employed">Self-employed</option>
                                <option value="Unemployed">Unemployed</option>
                            </select>
                            <x-input-error :messages="$errors->get('employment_status')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Net Monthly Salary -->
                        <div class="space-y-2 mt-3">
                            <x-input-label for="net_salary" :value="__('Net Monthly Salary/Wages')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="net_salary"
                                type="number"
                                name="net_salary"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="e.g. 1000"                                 required

                                min="0"
                                aria-describedby="salaryHelp"
                            />
                            <x-input-error :messages="$errors->get('net_salary')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Salary Frequency -->
                        <div class="space-y-2">
                            <x-input-label for="salary_frequency" :value="__('Salary/Wages Frequency')" class="text-base sm:text-lg" />
                            <select
                                id="salary_frequency"
                                name="salary_frequency"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"                                 required

                            >
                                <option value="" disabled selected>Select Frequency</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Bi-weekly">Bi-weekly</option>
                            </select>
                            <x-input-error :messages="$errors->get('salary_frequency')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Salary Payment Day -->
                        <div class="space-y-2 mt-3">
                            <x-input-label for="salary_payment_day" :value="__('Salary Date')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="salary_payment_day"
                                type="number"
                                name="salary_payment_day"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="e.g. 25"
                                min="1"                                 required

                                max="31"
                                aria-describedby="salaryHelp"
                            />
                            <x-input-error :messages="$errors->get('salary_payment_day')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Credit Score 
                        <div class="space-y-2 mt-3">
                            <x-input-label for="credit_score" :value="__('Credit Score')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="credit_score"
                                type="number"
                                name="credit_score"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                placeholder="e.g. 650"
                                min="0"
                                max="850"
                                aria-describedby="creditScoreHelp"
                            />
                            <x-input-error :messages="$errors->get('credit_score')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>
                    </section> -->

                    <!-- Account Security Section -->
                    <section class="space-y-6">
                        <h2 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-4
                            text-base sm:text-2xl">
                            Account Security
                        </h2>

                        <!-- Password -->
                        <div class="space-y-2">
                            <x-input-label for="password" :value="__('Password')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="password"
                                type="password"
                                name="password"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                required
                                aria-describedby="passwordHelp"
                            />
                            <div class="text-muted text-xs sm:text-sm mt-1">
                                Password must be at least 8 characters long and include a mix of letters, numbers, and special characters.
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-2 mt-3">
                            <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-base sm:text-lg" />
                            <x-text-input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                required
                                aria-describedby="confirmPasswordHelp"
                            />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>
                    </section>

                    <!-- File Upload Section -->
                    <section class="space-y-6">
                        <h2 class="text-2xl font-semibold text-gray-600 dark:text-gray-300 mb-4
                            text-base sm:text-2xl">
                            Upload Files
                        </h2>

                        <!-- ID Copy -->
                        <div class="space-y-2">
                            <x-input-label for="ID_copy" :value="__('Upload ID Copy')" class="text-base sm:text-lg" />
                            <input
                                id="ID_copy"
                                type="file"
                                name="ID_copy"
                                class="w-full px-3 py-2 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm sm:text-base"
                                required
                                aria-describedby="IDcopyHelp"
                            />
                            <small class="form-text text-muted text-xs sm:text-sm" id="IDcopyHelp">Please upload a clear copy of your ID (jpg, png, pdf).</small>
                            <x-input-error :messages="$errors->get('ID_copy')" class="text-xs sm:text-sm text-red-500 mt-1" />
                        </div>
                    </section>

                    <!-- Submit Button -->
                    <div class="mt-6 lg:col-span-2">
                        <x-primary-button class="w-full py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 text-sm sm:text-base">
                            {{ __('Register') }}
                        </x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- Alpine.js for accordion functionality --}}
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</x-guest-layout>
