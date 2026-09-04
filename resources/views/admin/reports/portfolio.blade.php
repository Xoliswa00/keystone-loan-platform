<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Portfolio Report</span>
    <p class="kc-page-subtitle">Loan book overview · {{ now()->format('d F Y') }}</p>
  </x-slot>

  {{-- KPI summary row --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Capital Deployed</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($principalDeployed, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Total Outstanding</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($totalOutstanding, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">PAR (Overdue %)</p>
      <p class="font-display text-2xl font-bold {{ $parPercentage > 10 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ number_format($parPercentage, 1) }}%</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Collection Rate</p>
      <p class="font-display text-2xl font-bold {{ $collectionRate < 85 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ number_format($collectionRate, 1) }}%</p>
    </div>
  </div>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Overdue Exposure</p>
      <p class="font-display text-xl font-bold text-red-600 mt-1">R {{ number_format($overdueAmount, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">ECL Provision</p>
      <p class="font-display text-xl font-bold text-orange-600 mt-1">R {{ number_format($totalProvision ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Avg Loan Size</p>
      <p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($averageLoan ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Portfolio Growth (MoM)</p>
      <p class="font-display text-xl font-bold {{ $monthlyGrowth >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">{{ number_format($monthlyGrowth, 1) }}%</p>
      <p class="text-xs text-kc-charcoal/60 mt-0.5">R{{ number_format($newDisbursementsThisMonth ?? 0, 0) }} disbursed this month</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    {{-- IFRS 9 staging --}}
    @if(isset($ifrs9Staging) && $ifrs9Staging->isNotEmpty())
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">IFRS 9 Staging</h4>
      <div class="space-y-3">
        @foreach($ifrs9Staging as $stage)
        @php $cls = $stage->ifrs9_stage == 3 ? 'bg-red-500' : ($stage->ifrs9_stage == 2 ? 'bg-orange-400' : 'bg-emerald-500'); @endphp
        <div class="flex items-center gap-3">
          <span class="w-16 text-xs font-bold text-kc-charcoal/60">Stage {{ $stage->ifrs9_stage }}</span>
          <div class="flex-1 h-2 bg-kc-silver-light rounded-full overflow-hidden">
            @php $pct = $totalOutstanding > 0 ? ($stage->outstanding / $totalOutstanding * 100) : 0; @endphp
            <div class="h-full {{ $cls }} rounded-full" style="width:{{ $pct }}%"></div>
          </div>
          <span class="text-xs font-semibold w-24 text-right">R {{ number_format($stage->outstanding, 0) }}</span>
          <span class="text-xs text-kc-charcoal/60 w-16 text-right">{{ $stage->count }} loans</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Status breakdown --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Book Breakdown by Status</h4>
      <div class="kc-table-scroll">
        <table class="kc-table">
          <thead><tr><th>Status</th><th>Count</th><th>Amount</th></tr></thead>
          <tbody>
            @foreach($statusBreakdown as $row)
            <tr>
              <td data-label="Status"><span class="kc-badge kc-badge-silver">{{ ucfirst($row->status) }}</span></td>
              <td data-label="Count">{{ $row->loan_count }}</td>
              <td data-label="Amount" class="font-semibold">R {{ number_format($row->amount, 2) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Top overdue --}}
  <div class="kc-card mb-6">
    <h4 class="font-display font-semibold text-kc-navy mb-4">Top 10 Risk Exposures</h4>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Loan</th><th>Client</th><th>Outstanding</th><th>Days Late</th></tr></thead>
        <tbody>
          @forelse($topOverdue as $row)
          <tr>
            <td data-label="Loan" class="font-mono text-xs">#{{ str_pad($row->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Client">
              <div class="font-semibold">{{ $row->client_name }}</div>
              <div class="text-xs text-kc-charcoal/60">{{ $row->customer_code }}</div>
            </td>
            <td data-label="Outstanding" class="font-semibold text-red-600">R {{ number_format($row->outstanding,2) }}</td>
            <td data-label="Days Late" class="font-bold {{ $row->days_late >= 90 ? 'text-red-600' : 'text-orange-500' }}">
              {{ $row->days_late }} DPD
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="text-center py-6 text-kc-charcoal/60">No overdue accounts.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Monthly activity --}}
  <div class="kc-card">
    <h4 class="font-display font-semibold text-kc-navy mb-4">Monthly Disbursement Activity</h4>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Month</th><th>Loans</th><th>Total Disbursed</th></tr></thead>
        <tbody>
          @foreach($monthlyActivity as $row)
          <tr>
            <td data-label="Month">{{ $row->month }}</td>
            <td data-label="Loans">{{ $row->num_loans }}</td>
            <td data-label="Disbursed" class="font-semibold">R {{ number_format($row->total_disbursed,2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
