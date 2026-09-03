<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Profitability Report</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  {{-- Date filter --}}
  <form method="GET" class="flex flex-wrap gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  {{-- KPI cards --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Principal Deployed</span>
      <p class="font-display text-2xl font-semibold text-kc-navy mt-2">R {{ number_format($totalPrincipalDisbursed, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Gross Income</span>
      <p class="font-display text-2xl font-semibold text-kc-navy mt-2">R {{ number_format($totalGrossIncome, 2) }}</p>
      <p class="text-xs text-kc-charcoal/60 mt-1">Interest + Fees recognised (GL), excl. VAT</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Collected</span>
      <p class="font-display text-2xl font-semibold text-emerald-600 mt-2">R {{ number_format($totalCollected, 2) }}</p>
      <p class="text-xs text-kc-charcoal/60 mt-1">Cash received in period</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Net Revenue</span>
      <p class="font-display text-2xl font-semibold {{ $netRevenue >= 0 ? 'text-kc-navy' : 'text-red-600' }} mt-2">R {{ number_format($netRevenue, 2) }}</p>
      <p class="text-xs text-kc-charcoal/60 mt-1">Collected − ECL − Bank charges</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    {{-- Income breakdown --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Income Breakdown</h4>
      <p class="text-xs text-kc-charcoal/60 mb-3">Recognised (GL), matches the Income Statement for this period.</p>
      <table class="kc-table">
        <tbody>
          <tr><td>Interest Income <span class="kc-badge kc-badge-silver text-[10px] ml-1">VAT exempt</span></td><td class="text-right font-semibold">R {{ number_format($interestIncome, 2) }}</td></tr>
          <tr><td>Fee Income (excl. VAT)</td><td class="text-right font-semibold">R {{ number_format($feeIncome, 2) }}</td></tr>
          <tr><td>VAT on Fees</td><td class="text-right text-kc-charcoal/50">R {{ number_format($vatOnFees, 2) }}</td></tr>
          <tr class="border-t-2 border-kc-gold/30 font-semibold"><td>Total Gross Income</td><td class="text-right text-kc-gold">R {{ number_format($totalGrossIncome, 2) }}</td></tr>
        </tbody>
      </table>
    </div>

    {{-- Ratios --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Key Ratios</h4>
      <table class="kc-table">
        <tbody>
          <tr><td>Portfolio Yield</td><td class="text-right font-semibold">{{ number_format($portfolioYield, 1) }}%</td></tr>
          <tr><td>Avg Gross Margin</td><td class="text-right font-semibold">{{ number_format($avgMargin, 1) }}%</td></tr>
          <tr><td>Cost of Risk (ECL/Principal)</td><td class="text-right font-semibold {{ $costOfRisk > 5 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($costOfRisk, 2) }}%</td></tr>
          <tr><td>Overdue Exposure</td><td class="text-right font-semibold text-orange-600">R {{ number_format($overdueAmount, 2) }}</td></tr>
          <tr><td>Failed Payments</td><td class="text-right font-semibold text-red-600">R {{ number_format($failedPayments, 2) }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Deferred income note --}}
  @if($totalDeferredInterest > 0 || $totalDeferredFees > 0)
  <div class="kc-card mb-6">
    <h4 class="font-semibold text-kc-navy mb-3">Deferred Income (Balance Sheet Liabilities)</h4>
    <div class="grid grid-cols-2 gap-4">
      <div><p class="text-xs text-kc-charcoal/50">Deferred Interest</p><p class="font-display text-lg font-semibold">R {{ number_format($totalDeferredInterest, 2) }}</p></div>
      <div><p class="text-xs text-kc-charcoal/50">Deferred Fees</p><p class="font-display text-lg font-semibold">R {{ number_format($totalDeferredFees, 2) }}</p></div>
    </div>
    <p class="text-xs text-kc-charcoal/60 mt-2">Carried on balance sheet — recognised as instalments are collected on multi-month loans.</p>
  </div>
  @endif

  {{-- Monthly revenue trend --}}
  <div class="kc-card">
    <h4 class="font-display font-semibold text-kc-navy mb-4">Monthly Revenue Trend</h4>
    <table class="kc-table">
      <thead><tr><th>Month</th><th>Payments</th><th>Interest Recognised</th><th>Fees Recognised</th><th>Total Revenue</th></tr></thead>
      <tbody>
        @forelse($monthlyRevenue as $row)
        <tr>
          <td>{{ $row->month }}</td>
          <td>{{ $row->payments_count }}</td>
          <td>R {{ number_format($row->interest_income, 2) }}</td>
          <td>R {{ number_format($row->fee_income, 2) }}</td>
          <td class="font-semibold">R {{ number_format($row->total_revenue, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-kc-charcoal/60 py-6">No payment data in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-app-layout>
