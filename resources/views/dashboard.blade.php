<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <span class="kc-page-title">Dashboard</span>
        </div>
    </x-slot>

@php
    $user   = Auth::user();
    $loan   = $user->loanApplications()->where('status', 'approved')->latest()->first();
    $next   = $loan ? $loan->repaymentSchedules()->where('status', 'pending')->orderBy('due_date')->first() : null;
    $balance = $user->customer->current_balance ?? 0;
    $userId  = $user->customer->customer_code ?? $user->id;
    $bizWA   = '27721853349';
    $supWA   = '27674017419';

    $hour     = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    $outstandingShortfall = 0.0;
    $outstandingCredit    = 0.0;
    if ($user->customer) {
        $paymentAdjustments   = app(\App\Services\PaymentAdjustmentService::class);
        $outstandingShortfall = $paymentAdjustments->outstandingShortfall($user->customer);
        $outstandingCredit    = $paymentAdjustments->outstandingCredit($user->customer);
    }
@endphp

{{-- ── Welcome banner ── --}}
<div class="kc-card-navy mb-6 animate-fadeIn relative overflow-hidden">
    {{-- subtle gold glow --}}
    <div class="absolute -top-8 -right-8 w-40 h-40 bg-kc-gold opacity-10 rounded-full blur-2xl pointer-events-none"></div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 relative z-10">
        <div>
            <p class="text-white/50 text-xs font-medium uppercase tracking-widest mb-1">{{ $greeting }}</p>
            <h2 class="font-display text-2xl font-semibold text-white">{{ $user->name }}</h2>
            <p class="text-white/40 text-sm mt-1">
                {{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Client #{{ $userId }}
            </p>
        </div>
        <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Loan Application
        </a>
    </div>
</div>

{{-- ── Summary cards ── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Outstanding balance --}}
    <div class="kc-stat-card animate-fadeIn" style="animation-delay:0.05s">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Outstanding</span>
            <div class="w-8 h-8 rounded-lg bg-kc-navy/8 flex items-center justify-center">
                <svg class="w-4 h-4 text-kc-navy" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
        </div>
        <p class="font-display text-2xl font-semibold text-kc-navy">R{{ number_format($balance, 2) }}</p>
        <p class="text-xs text-kc-charcoal/40 mt-1">Total outstanding balance</p>
    </div>

    {{-- Next instalment --}}
    <div class="kc-stat-card animate-fadeIn" style="animation-delay:0.1s">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Next Instalment</span>
            <div class="w-8 h-8 rounded-lg bg-kc-gold/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-kc-gold-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="font-display text-2xl font-semibold text-kc-navy">R{{ number_format($next->emi_amount ?? 0, 2) }}</p>
        <p class="text-xs text-kc-charcoal/40 mt-1">Scheduled instalment</p>
    </div>

    {{-- Due date --}}
    <div class="kc-stat-card animate-fadeIn" style="animation-delay:0.15s">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Due Date</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
        <p class="font-display text-2xl font-semibold text-kc-navy">
            @if($next)
                {{ \Carbon\Carbon::parse($next->due_date)->format('d M') }}
            @else
                <span class="text-kc-silver">—</span>
            @endif
        </p>
        <p class="text-xs text-kc-charcoal/40 mt-1">
            @if($next)
                {{ \Carbon\Carbon::parse($next->due_date)->format('Y') }} &nbsp;·&nbsp;
                @if(\Carbon\Carbon::parse($next->due_date)->isPast())
                    <span class="text-red-500 font-medium">Overdue</span>
                @else
                    in {{ \Carbon\Carbon::parse($next->due_date)->diffForHumans(null, true) }}
                @endif
            @else
                No payment due
            @endif
        </p>
    </div>

    {{-- Account status --}}
    <div class="kc-stat-card animate-fadeIn" style="animation-delay:0.2s">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Status</span>
            <div class="w-8 h-8 rounded-lg bg-kc-silver-light flex items-center justify-center">
                <svg class="w-4 h-4 text-kc-charcoal/50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
        </div>
        @php
            $status = $user->status ?? 'active';
            $statusClass = match(strtolower($status)) {
                'active'   => 'kc-badge-green',
                'inactive' => 'kc-badge-silver',
                'suspended'=> 'kc-badge-red',
                default    => 'kc-badge-silver',
            };
        @endphp
        <p class="mt-1"><span class="kc-badge {{ $statusClass }} text-sm px-3 py-1">{{ ucfirst($status) }}</span></p>
        <p class="text-xs text-kc-charcoal/40 mt-2">Account standing</p>
    </div>
</div>

{{-- ── Outstanding shortfall / credit — same disclosure shown on the
     application form and agreement documents ── --}}
@if($outstandingShortfall > 0)
<div class="mb-6 flex items-start gap-3 px-5 py-4 rounded-xl border border-amber-300/40 bg-amber-50">
    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <div>
        <p class="text-sm font-semibold text-amber-800">Outstanding balance from a previous loan: R{{ number_format($outstandingShortfall, 2) }}</p>
        <p class="text-xs text-amber-700/80 mt-0.5">This is disclosed on your loan agreement and does not affect your current instalments.</p>
    </div>
</div>
@endif

@if($outstandingCredit > 0)
<div class="mb-6 flex items-start gap-3 px-5 py-4 rounded-xl border border-emerald-300/40 bg-emerald-50">
    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
    </svg>
    <div>
        <p class="text-sm font-semibold text-emerald-800">Credit balance: R{{ number_format($outstandingCredit, 2) }}</p>
        <p class="text-xs text-emerald-700/80 mt-0.5">From a previous overpayment — this will be applied to your next instalment.</p>
    </div>
</div>
@endif

{{-- ── Quick actions ── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <a href="{{ route('profile.edit') }}"
        class="kc-card hover:shadow-md transition group flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-kc-navy/8 flex items-center justify-center group-hover:bg-kc-navy transition">
            <svg class="w-5 h-5 text-kc-navy group-hover:text-white transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-kc-navy">Profile</p>
            <p class="text-xs text-kc-charcoal/40">Personal details</p>
        </div>
    </a>

    @php $msgBiz = urlencode("Hi, I am {$user->name} (Client ID: {$userId}). I'd like to enquire about your services."); @endphp
    <a href="https://wa.me/{{ $bizWA }}?text={{ $msgBiz }}" target="_blank"
        class="kc-card hover:shadow-md transition group flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-500 transition">
            <svg class="w-5 h-5 text-emerald-600 group-hover:text-white transition" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.524 5.845L0 24l6.295-1.507A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.007-1.373l-.36-.213-3.727.892.924-3.618-.235-.372A9.818 9.818 0 1112 21.818z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-kc-navy">Enquiries</p>
            <p class="text-xs text-kc-charcoal/40">Chat with us</p>
        </div>
    </a>

    <a href="{{ route('client.my-statement') }}" class="kc-card hover:shadow-md transition group flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-kc-gold/10 flex items-center justify-center group-hover:bg-kc-gold transition">
            <svg class="w-5 h-5 text-kc-gold-muted group-hover:text-white transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-kc-navy">Statements</p>
            <p class="text-xs text-kc-charcoal/40">Account statements</p>
        </div>
    </a>

    @php $msgSup = urlencode("Hi, I am {$user->name} (Client ID: {$userId}). I need assistance."); @endphp
    <a href="https://wa.me/{{ $supWA }}?text={{ $msgSup }}" target="_blank"
        class="kc-card hover:shadow-md transition group flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center group-hover:bg-red-500 transition">
            <svg class="w-5 h-5 text-red-500 group-hover:text-white transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-kc-navy">Support</p>
            <p class="text-xs text-kc-charcoal/40">Get assistance</p>
        </div>
    </a>
</div>

{{-- ── Recent loans ── --}}
<div class="kc-card animate-fadeIn" style="animation-delay:0.25s">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h3 class="font-display text-base font-semibold text-kc-navy">My Loans</h3>
            <p class="text-xs text-kc-charcoal/40 mt-0.5">Your active and recent loan agreements</p>
        </div>
        <a href="{{ route('loan.index') }}" class="text-xs text-kc-gold hover:text-kc-gold-muted transition font-medium">
            View all
        </a>
    </div>

    @php
        $loans = $user->loanApplications()->latest()->take(5)->get();
    @endphp

    @if($loans->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <div class="w-12 h-12 rounded-full bg-kc-silver-light flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-kc-silver" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-kc-charcoal/60">No loans yet</p>
            <p class="text-xs text-kc-charcoal/40 mt-1">Your loan applications will appear here</p>
            <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary mt-4 text-xs">
                Apply for a Loan
            </a>
        </div>
    @else
        <div class="overflow-x-auto -mx-6 px-6">
            <table class="kc-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loans as $la)
                    @php
                        $badgeClass = match(strtolower($la->status)) {
                            'approved', 'disbursed' => 'kc-badge-green',
                            'pending'               => 'kc-badge-gold',
                            'rejected'              => 'kc-badge-red',
                            default                 => 'kc-badge-silver',
                        };
                    @endphp
                    <tr>
                        <td class="font-mono text-xs text-kc-charcoal/60">
                            #{{ str_pad($la->id, 6, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="font-semibold">R{{ number_format($la->loan_amount, 2) }}</td>
                        <td class="capitalize">{{ $la->loan_type ?? '—' }}</td>
                        <td><span class="kc-badge {{ $badgeClass }}">{{ ucfirst($la->status) }}</span></td>
                        <td class="text-kc-charcoal/50">{{ \Carbon\Carbon::parse($la->created_at)->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('loanapplications.show', $la->id) }}"
                                class="text-xs text-kc-gold hover:text-kc-gold-muted transition font-medium">
                                View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ── Pro tip ── --}}
<div class="mt-6 flex items-start gap-3 px-5 py-4 rounded-xl border border-kc-gold/20 bg-kc-gold/5 animate-fadeIn" style="animation-delay:0.3s">
    <div class="w-6 h-6 rounded-full bg-kc-gold/20 flex items-center justify-center flex-shrink-0 mt-0.5">
        <svg class="w-3.5 h-3.5 text-kc-gold" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
    </div>
    <div>
        <p class="text-xs font-semibold text-kc-gold-muted uppercase tracking-wide">Tip</p>
        <p class="text-xs text-kc-charcoal/60 mt-0.5">
            Keep your debit order active and ensure sufficient funds before your due date to maintain a healthy credit profile.
        </p>
    </div>
</div>

</x-app-layout>
