<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 justify-between items-center">

            {{-- LEFT --}}
            <div class="flex items-center gap-x-8">
                <a href="{{ route('dashboard') }}">
                    <x-application-logo class="h-12 w-auto text-blue-600 dark:text-blue-400"/>
                </a>

                {{-- DESKTOP --}}
                <div class="hidden sm:flex items-center gap-x-10">
                    @auth
                        

                        @if(Auth::user()->rule_id === 2)
                        
                         <x-nav-link :href="route('dashboard')"> 🏠  Admin Dashboard</x-nav-link>
                                                   <x-nav-link :href="route('admin.loans')"> 🛡 Loan Approval</x-nav-link>

                        <x-nav-link :href="route('repaymentSchedules.index')">💳 Repayments</x-nav-link>
                            <x-nav-link :href="route('customers.index')"> 👥  Customers</x-nav-link>
                                                        <x-nav-link :href="route('reports.portfolio')"> Reporting</x-nav-link>

                               <x-nav-link :href="route('nu-pay.import.index')" :active="request()->routeIs('nu-pay.import.*')">
                                {{ __('📊 Imports') }}
                            </x-nav-link>
                            <x-nav-link :href="route('loan.index')"> 💼 Personal Loans</x-nav-link>
                            @else
                            <x-nav-link :href="route('dashboard')">Dashboard</x-nav-link>
                        <x-nav-link :href="route('loan.index')">Loans</x-nav-link>
                        <x-nav-link :href="route('repaymentSchedules.index')">Repayments</x-nav-link>
 @endif
                    @endauth
                </div>
            </div>

            {{-- RIGHT --}}
            @auth
                <div class="hidden sm:flex">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 text-sm">
                                {{ Auth::user()->name }}
                                <span>▾</span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                            <x-dropdown-link :href="route('accountdetails.index')">Account</x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @endauth

            {{-- MOBILE BUTTON --}}
            <button @click="open = !open" class="sm:hidden p-2 rounded-md text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!open" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="open" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ✅ MOBILE MENU (FIXED) --}}
    <div
        x-show="open"
        x-transition
        @click.outside="open = false"
        class="sm:hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700"
    >
        <div class="px-4 py-4 space-y-3">
            @auth
             

                @if(Auth::user()->rule_id === 2)
                    <hr>
                        <x-nav-link :href="route('dashboard')">🏠 Dashboard</x-nav-link>
                                            <x-nav-link :href="route('admin.loans')">🛡 Loan Approval</x-nav-link>

                <x-nav-link :href="route('repaymentSchedules.index')">💳 Repayments</x-nav-link>
                    <x-nav-link :href="route('customers.index')">👥 Customers</x-nav-link>
                                        <x-nav-link :href="route('customers.index')">👥 Customers</x-nav-link>

                    <x-nav-link :href="route('admin.loans')">🛡 Loan Approval</x-nav-link>
                                    <x-nav-link :href="route('loan.index')">💼  Persoanl Loans</x-nav-link>

                    
                    @else
                       <x-nav-link :href="route('dashboard')">🏠 Dashboard</x-nav-link>
                <x-nav-link :href="route('loan.index')">💼 Loans</x-nav-link>
                <x-nav-link :href="route('repaymentSchedules.index')">💳 Repayments</x-nav-link>
                @endif

                <hr>
                <x-nav-link :href="route('profile.edit')">👤 Profile</x-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        🚪 Log Out
                    </x-nav-link>
                </form>
            @else
                <x-nav-link :href="route('login')">Sign In</x-nav-link>
                <x-nav-link :href="route('register')">Sign Up</x-nav-link>
            @endauth
        </div>
    </div>
</nav>
