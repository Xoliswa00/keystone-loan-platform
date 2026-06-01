<x-app-layout>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">

        {{-- Sidebar --}}
        <aside class="w-64 bg-white dark:bg-gray-800 border-r">
            <div class="p-4 font-semibold border-b">
                Reports Center
            </div>

            <nav class="p-3 space-y-1 text-sm">
                                <x-report-link route="reports.summary" label="Profitability Summary" />

                <x-report-link route="reports.portfolio" label="Portfolio Summary" />
                <x-report-link route="reports.arrears" label="Arrears & PAR" />
                <x-report-link route="reports.scorecard" label="Scorecard" />
                <x-report-link route="reports.disbursements" label="Disbursements" />
                <x-report-link route="reports.gl-recon" label="GL Reconciliation" />
                <x-report-link route="reports.reversals" label="Reversal Audit" />
            </nav>
        </aside>

        {{-- Main --}}
        <div class="flex-1 flex flex-col">

            <div class="p-4 border-b bg-white dark:bg-gray-800">
                <h2 class="text-lg font-semibold">
                    @yield('report-title')
                </h2>
            </div>

            <main class="flex-1 p-6">
                @yield('report-content')
                
                    @stack('scripts') <!-- THIS IS REQUIRED -->

            </main>

        </div>
    </div>
</x-app-layout>
