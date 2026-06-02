<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Operations Dashboard</span>
    <p class="kc-page-subtitle">{{ now()->format('l, d F Y') }}</p>
  </x-slot>

  {{-- KPI cards --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Pending Applications</span>
      <p class="font-display text-3xl font-bold text-kc-gold mt-2">{{ $pendingLoansCount }}</p>
      <a href="{{ route('admin.loans') }}" class="text-xs text-kc-gold hover:underline mt-1 block">Review →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Awaiting Disbursement</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $totalLoansDisbursed }}</p>
      <a href="{{ route('disbursements.index') }}" class="text-xs text-kc-gold hover:underline mt-1 block">Approve →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Overdue Accounts</span>
      <p class="font-display text-3xl font-bold {{ $overdueLoansCount > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-2">{{ $overdueLoansCount }}</p>
      <a href="{{ route('reports.arrears') }}" class="text-xs text-kc-gold hover:underline mt-1 block">View →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Active Clients</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $customerCount }}</p>
      <a href="{{ route('customers.index') }}" class="text-xs text-kc-gold hover:underline mt-1 block">View →</a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Recent applications --}}
    <div class="kc-card">
      <div class="flex items-center justify-between mb-4">
        <h4 class="font-display font-semibold text-kc-navy">Recent Applications</h4>
        <a href="{{ route('admin.loans') }}" class="text-xs text-kc-gold hover:underline">View all</a>
      </div>
      <div class="kc-table-scroll">
        <table class="kc-table">
          <thead><tr><th>Client</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
          <tbody>
            @forelse($recentApplications as $app)
            @php
              $sc = match(strtolower($app->status)) {
                'approved','disbursed' => 'kc-badge-green',
                'pending','under_review' => 'kc-badge-gold',
                'rejected' => 'kc-badge-red',
                default => 'kc-badge-silver'
              };
            @endphp
            <tr>
              <td data-label="Client" class="font-semibold">{{ $app->user?->name ?? '—' }}</td>
              <td data-label="Amount">R {{ number_format($app->loan_amount, 2) }}</td>
              <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst($app->status) }}</span></td>
              <td data-label="Date" class="text-kc-charcoal/50 text-xs">{{ $app->created_at->format('d M') }}</td>
              <td data-label=""><a href="{{ route('Admin.show', $app->id) }}" class="text-xs text-kc-gold hover:underline">Review</a></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center py-6 text-kc-charcoal/40">No applications yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Quick links --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Quick Access</h4>
      <div class="grid grid-cols-2 gap-3">
        @foreach([
          ['route'=>'admin.loans',         'label'=>'Loan Approvals',   'icon'=>'check-shield'],
          ['route'=>'disbursements.index', 'label'=>'Disbursements',    'icon'=>'cash'],
          ['route'=>'reports.portfolio',   'label'=>'Portfolio',        'icon'=>'chart-bar'],
          ['route'=>'admin.collections',   'label'=>'Collections',      'icon'=>'phone'],
          ['route'=>'reports.npl',         'label'=>'NPL Report',       'icon'=>'warning'],
          ['route'=>'nu-pay.import.index', 'label'=>'Nu-Pay Import',    'icon'=>'upload'],
          ['route'=>'reports.scorecard',   'label'=>'Scorecard',        'icon'=>'user'],
          ['route'=>'reports.audit-log',   'label'=>'Audit Log',        'icon'=>'clipboard'],
        ] as $link)
        <a href="{{ route($link['route']) }}"
           class="flex items-center gap-2 p-3 rounded-xl border border-kc-silver-light hover:border-kc-gold/40 hover:bg-kc-gold/5 transition">
          <div class="w-7 h-7 rounded-lg bg-kc-navy/8 flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-kc-navy" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <span class="text-sm font-medium text-kc-charcoal">{{ $link['label'] }}</span>
        </a>
        @endforeach
      </div>
    </div>
  </div>
</x-app-layout>
