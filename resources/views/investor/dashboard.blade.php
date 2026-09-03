<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Investor Portal — Keystone Capital Partners</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-kc-silver-light/30">

    {{-- Header --}}
    <div class="bg-kc-navy px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
                <span class="font-display font-bold text-kc-gold text-sm">KC</span>
            </div>
            <div>
                <p class="text-white font-display font-semibold text-sm leading-tight">Keystone Capital Partners</p>
                <p class="text-kc-gold text-[10px] tracking-[0.2em] uppercase">Investor Portal</p>
            </div>
        </div>
        <form method="POST" action="{{ route('investor.logout') }}">
            @csrf
            <button type="submit" class="kc-btn-ghost text-xs text-white/70 hover:text-white border-white/20">
                Log out
            </button>
        </form>
    </div>

    <div class="max-w-5xl mx-auto p-6 space-y-6">

        <div>
            <h1 class="font-display text-2xl font-semibold text-kc-navy">Your Facilities</h1>
            <p class="text-sm text-kc-navy/60 mt-1">Read-only view of your funding facility balance and transaction history.</p>
        </div>

        @if (session('success'))
            <div class="kc-alert-success">{{ session('success') }}</div>
        @endif

        @forelse($facilities as $facility)
        @php
            $sc = \App\Models\FundingFacility::STATUS_CLASSES[$facility->status] ?? 'kc-badge-silver';
        @endphp
        <div class="kc-card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="font-display font-semibold text-kc-navy">{{ $facility->facility_code }} — {{ $facility->facility_name }}</h4>
                    <p class="text-xs text-kc-charcoal/50">{{ \App\Models\FundingFacility::FUNDER_TYPES[$facility->funder_type] ?? '' }}</p>
                </div>
                <span class="kc-badge {{ $sc }}">{{ ucfirst(str_replace('_', ' ', $facility->status)) }}</span>
            </div>

            {{-- KPIs --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="kc-stat-card text-center">
                    <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Facility Limit</p>
                    <p class="font-display text-lg font-bold text-kc-navy mt-1">R {{ number_format($facility->facility_limit, 2) }}</p>
                </div>
                <div class="kc-stat-card text-center">
                    <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Outstanding</p>
                    <p class="font-display text-lg font-bold text-kc-navy mt-1">R {{ number_format($facility->current_balance, 2) }}</p>
                </div>
                <div class="kc-stat-card text-center">
                    <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Available</p>
                    <p class="font-display text-lg font-bold text-emerald-600 mt-1">R {{ number_format($facility->availableLimit(), 2) }}</p>
                </div>
                <div class="kc-stat-card text-center">
                    <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Accrued Interest</p>
                    <p class="font-display text-lg font-bold {{ $facility->accrued_interest > 0 ? 'text-orange-600' : 'text-kc-navy' }} mt-1">R {{ number_format($facility->accrued_interest, 2) }}</p>
                </div>
            </div>

            {{-- Utilisation --}}
            <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-kc-navy">Utilisation</span>
                    <span class="text-sm font-bold text-kc-gold">{{ $facility->utilisationRate() }}%</span>
                </div>
                <div class="h-2.5 w-full bg-kc-silver-light rounded-full overflow-hidden">
                    <div class="h-full {{ $facility->utilisationRate() > 90 ? 'bg-red-500' : 'bg-kc-gold' }} rounded-full transition-all"
                         style="width:{{ min(100, $facility->utilisationRate()) }}%"></div>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3 text-center text-xs">
                    <div><p class="text-kc-charcoal/50">Monthly Interest Due</p><p class="font-semibold text-kc-navy">R {{ number_format($facility->monthlyInterestDue(), 2) }}</p></div>
                    <div><p class="text-kc-charcoal/50">Rate p.a.</p><p class="font-semibold">{{ round($facility->interest_rate * 100, 2) }}%</p></div>
                    <div><p class="text-kc-charcoal/50">Maturity</p><p class="font-semibold">{{ $facility->maturity_date?->format('d M Y') ?? 'Revolving' }}</p></div>
                </div>
            </div>

            {{-- Transactions --}}
            <div>
                <h5 class="font-semibold text-kc-navy text-sm mb-2">Transaction History</h5>
                <div class="kc-table-scroll">
                    <table class="kc-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Reference</th></tr></thead>
                        <tbody>
                            @forelse($facility->transactions as $txn)
                            @php $tc = \App\Models\FundingFacilityTransaction::TYPE_CLASSES[$txn->transaction_type] ?? 'kc-badge-silver'; @endphp
                            <tr>
                                <td data-label="Date">{{ \Carbon\Carbon::parse($txn->transaction_date)->format('d M Y') }}</td>
                                <td data-label="Type"><span class="kc-badge {{ $tc }}">{{ \App\Models\FundingFacilityTransaction::TYPE_LABELS[$txn->transaction_type] ?? $txn->transaction_type }}</span></td>
                                <td data-label="Amount" class="font-semibold {{ $txn->transaction_type === 'drawdown' ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $txn->transaction_type === 'drawdown' ? '+' : '-' }} R {{ number_format($txn->amount, 2) }}
                                </td>
                                <td data-label="Ref" class="text-xs">{{ $txn->reference ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-6 text-kc-charcoal/60">No transactions yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @empty
        <div class="kc-card text-center py-10 text-kc-charcoal/50">
            No facilities found on your account.
        </div>
        @endforelse

        <p class="text-center text-xs text-kc-charcoal/60 pb-6">
            &copy; {{ date('Y') }} Keystone Capital Partners. All rights reserved.
        </p>
    </div>

</body>
</html>
