<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Operations Dashboard</span>
    <p class="kc-page-subtitle">{{ now()->format('l, d F Y') }}</p>
  </x-slot>

  @php
    $seesLoanOps = in_array($role, ['admin', 'loan_officer', 'viewer'], true);
    $seesFinance = in_array($role, ['admin', 'finance', 'viewer'], true);
    $seesIT = in_array($role, ['admin', 'it_admin', 'viewer'], true);
  @endphp

  {{-- KPI cards — cross-cutting first, then role-specific --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Pending Applications</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $pendingLoansCount }}</p>
      <a href="{{ route('admin.loans') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Review →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Overdue Accounts</span>
      <p class="font-display text-3xl font-bold {{ $overdueLoansCount > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-2">{{ $overdueLoansCount }}</p>
      <a href="{{ route('reports.arrears') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">View →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Active Clients</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $customerCount }}</p>
      <a href="{{ route('customers.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">View →</a>
    </div>

    @if($seesLoanOps)
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Awaiting Disbursement</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $totalLoansDisbursed }}</p>
      <a href="{{ route('disbursements.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Approve →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Pending Payment Verifications</span>
      <p class="font-display text-3xl font-bold {{ $pendingPaymentVerifications > 0 ? 'text-kc-navy' : 'text-emerald-600' }} mt-2">{{ $pendingPaymentVerifications }}</p>
      <a href="{{ route('admin.manual-payments.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Review →</a>
    </div>
    @endif

    @if($seesFinance)
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Unposted Nu-Pay Batches</span>
      <p class="font-display text-3xl font-bold {{ $unpostedNupayBatches > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-2">{{ $unpostedNupayBatches }}</p>
      <a href="{{ route('nu-pay.import.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Post →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Current Period</span>
      <p class="font-display text-lg font-bold {{ $currentPeriodOpen ? 'text-emerald-600' : 'text-red-600' }} mt-2">{{ $currentPeriodLabel }}</p>
      <a href="{{ route('admin.periods.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Manage →</a>
    </div>
    @endif

    @if($seesIT)
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Failed Jobs</span>
      <p class="font-display text-3xl font-bold {{ $failedJobsCount > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-2">{{ $failedJobsCount }}</p>
      <a href="{{ route('admin.system.logs') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Investigate →</a>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70">Staff Accounts</span>
      <p class="font-display text-3xl font-bold text-kc-navy mt-2">{{ $staffCount }}</p>
      <a href="{{ route('admin.staff.index') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-1 block">Manage →</a>
    </div>
    @endif
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    @if($seesLoanOps)
    {{-- Recent applications --}}
    <div class="kc-card">
      <div class="flex items-center justify-between mb-4">
        <h4 class="font-display font-semibold text-kc-navy">Recent Applications</h4>
        <a href="{{ route('admin.loans') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View all</a>
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
              <td data-label="Date" class="text-kc-charcoal/70 text-xs">{{ $app->created_at->format('d M') }}</td>
              <td data-label=""><a href="{{ route('Admin.show', $app->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Review</a></td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center py-6 text-kc-charcoal/70">No applications yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($recoveredNeedingFollowup > 0)
      <div class="mt-4 pt-4 border-t border-kc-silver-light/60 flex items-center justify-between">
        <span class="text-xs text-kc-charcoal/70">{{ $recoveredNeedingFollowup }} recovered debt-recovery case(s) may need a follow-up</span>
        <a href="{{ route('admin.recovery.index', ['status' => 'recovered']) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Review →</a>
      </div>
      @endif
    </div>
    @endif

    {{-- Quick links — role-scoped so nothing here links to a route this role can't open --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Quick Access</h4>
      <div class="grid grid-cols-2 gap-3">
        @php
          $quickLinks = [];
          if ($seesLoanOps) {
            $quickLinks = array_merge($quickLinks, [
              ['route'=>'admin.loans',         'label'=>'Loan Approvals'],
              ['route'=>'disbursements.index', 'label'=>'Disbursements'],
              ['route'=>'admin.collections',   'label'=>'Collections'],
              ['route'=>'admin.recovery.index','label'=>'Debt Recovery'],
              ['route'=>'admin.manual-payments.index', 'label'=>'Payment Verification'],
              ['route'=>'reports.portfolio',   'label'=>'Portfolio'],
              ['route'=>'reports.scorecard',   'label'=>'Scorecard'],
            ]);
          }
          if ($seesFinance) {
            $quickLinks = array_merge($quickLinks, [
              ['route'=>'nu-pay.import.index',      'label'=>'Nu-Pay Import'],
              ['route'=>'admin.finance.business-bank.upload', 'label'=>'Business Bank Recon'],
              ['route'=>'admin.periods.index',      'label'=>'Financial Periods'],
              ['route'=>'reports.profitability',    'label'=>'Profitability'],
              ['route'=>'reports.balance-sheet',    'label'=>'Balance Sheet'],
              ['route'=>'reports.npl',              'label'=>'NPL Report'],
            ]);
          }
          if ($seesIT) {
            $quickLinks = array_merge($quickLinks, [
              ['route'=>'admin.system.logs',  'label'=>'System Logs'],
              ['route'=>'admin.staff.index',  'label'=>'Staff Management'],
              ['route'=>'reports.audit-log',  'label'=>'Audit Report'],
            ]);
          }
        @endphp
        @foreach($quickLinks as $link)
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
