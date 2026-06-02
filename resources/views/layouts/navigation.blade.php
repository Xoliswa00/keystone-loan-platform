<nav class="kc-sidebar-nav">
    @auth
        @php $isAdmin = Auth::user()->rule_id === 2; @endphp

        @if($isAdmin)
            {{-- ── ADMIN NAVIGATION ── --}}

            <p class="kc-nav-section">Operations</p>

            <a href="{{ route('dashboard') }}"
                class="kc-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('admin.loans') }}"
                class="kc-nav-item {{ request()->routeIs('admin.loans*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Loan Approvals
            </a>

            <a href="{{ route('loan.index') }}"
                class="kc-nav-item {{ request()->routeIs('loan.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Personal Loans
            </a>

            <a href="{{ route('customers.index') }}"
                class="kc-nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Clients
            </a>

            <a href="{{ route('repaymentSchedules.index') }}"
                class="kc-nav-item {{ request()->routeIs('repaymentSchedules.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Repayments
            </a>

            <p class="kc-nav-section">Finance</p>

            <a href="{{ route('nu-pay.import.index') }}"
                class="kc-nav-item {{ request()->routeIs('nu-pay.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                Nu-Pay Imports
            </a>

            <p class="kc-nav-section">Capital & Finance</p>

            <a href="{{ route('admin.funding.index') }}"
                class="kc-nav-item {{ request()->routeIs('admin.funding.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                </svg>
                Funding Facilities
            </a>

            <a href="{{ route('admin.finance.business-bank.upload') }}"
                class="kc-nav-item {{ request()->routeIs('admin.finance.business-bank.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Business Bank
            </a>

            <a href="{{ route('admin.periods.index') }}"
                class="kc-nav-item {{ request()->routeIs('admin.periods.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Financial Periods
            </a>

            {{-- Reports with sub-menu --}}
            <div x-data="{ reportsOpen: {{ request()->routeIs('reports.*') || request()->routeIs('admin.reports*') ? 'true' : 'false' }} }">
                <button @click="reportsOpen = !reportsOpen"
                    class="kc-nav-item w-full justify-between {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Reports
                    </span>
                    <svg :class="reportsOpen ? 'rotate-90' : ''" class="w-3.5 h-3.5 text-white/40 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div x-show="reportsOpen" x-transition class="ml-4 mt-1 space-y-0.5 border-l border-white/10 pl-3">
                    <a href="{{ route('reports.portfolio') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.portfolio') ? 'active' : '' }}">
                        Portfolio
                    </a>
                    <a href="{{ route('reports.profitability') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.profitability') ? 'active' : '' }}">
                        Profitability
                    </a>
                    <a href="{{ route('reports.arrears') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.arrears') ? 'active' : '' }}">
                        Arrears
                    </a>
                    <a href="{{ route('reports.collections') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.collections') ? 'active' : '' }}">
                        Collections
                    </a>
                    <a href="{{ route('reports.disbursements') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.disbursements') ? 'active' : '' }}">
                        Disbursements
                    </a>
                    <a href="{{ route('reports.gl_summary') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.gl_summary') ? 'active' : '' }}">
                        GL Summary
                    </a>
                    <a href="{{ route('reports.income-statement') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.income-statement') ? 'active' : '' }}">
                        Income Statement
                    </a>
                    <a href="{{ route('reports.balance-sheet') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.balance-sheet') ? 'active' : '' }}">
                        Balance Sheet
                    </a>
                    <a href="{{ route('reports.vat201') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.vat201') ? 'active' : '' }}">
                        VAT 201
                    </a>
                    <a href="{{ route('reports.npl') }}"
                        class="kc-nav-item text-xs py-2 {{ request()->routeIs('reports.npl') ? 'active' : '' }}">
                        NPL / Arrears
                    </a>
                </div>
            </div>

            <p class="kc-nav-section">IT & System</p>

            <a href="{{ route('admin.settings.company') }}"
                class="kc-nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Company Settings
            </a>

            <a href="{{ route('admin.system.logs') }}"
                class="kc-nav-item {{ request()->routeIs('admin.system.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                System Logs
            </a>

        @else
            {{-- ── CLIENT NAVIGATION ── --}}

            <p class="kc-nav-section">My Account</p>

            <a href="{{ route('dashboard') }}"
                class="kc-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('loan.index') }}"
                class="kc-nav-item {{ request()->routeIs('loan.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                My Loans
            </a>

            <a href="{{ route('repaymentSchedules.index') }}"
                class="kc-nav-item {{ request()->routeIs('repaymentSchedules.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Repayment Schedule
            </a>

            <a href="{{ route('client.my-statement') }}"
                class="kc-nav-item {{ request()->routeIs('client.my-statement') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Statement
            </a>

            <a href="{{ route('loanapplications.create') }}"
                class="kc-nav-item {{ request()->routeIs('loanapplications.create') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Apply for a Loan
            </a>

            <p class="kc-nav-section">Support</p>

            <a href="{{ route('profile.edit') }}"
                class="kc-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profile
            </a>

            <a href="https://wa.me/27721853349" target="_blank" class="kc-nav-item">
                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.524 5.845L0 24l6.295-1.507A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.007-1.373l-.36-.213-3.727.892.924-3.618-.235-.372A9.818 9.818 0 1112 21.818z"/>
                </svg>
                WhatsApp Support
            </a>

        @endif
    @endauth
</nav>
