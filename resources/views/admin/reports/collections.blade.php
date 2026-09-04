<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Collections Report</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Payments</p><p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $totals->count ?? 0 }}</p></div>
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Total Collected</p><p class="font-display text-2xl font-bold text-emerald-600 mt-1">R {{ number_format($totals->total_collected ?? 0, 2) }}</p></div>
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Interest</p><p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($totals->total_interest ?? 0, 2) }}</p></div>
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Nu-Pay Fees</p><p class="font-display text-2xl font-bold text-kc-charcoal mt-1">R {{ number_format($totals->total_nupay_fees ?? 0, 2) }}</p></div>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Date</th><th>Client</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Nu-Pay Fee</th><th>Total</th><th>Method</th><th>GL Ref</th>
        </tr></thead>
        <tbody>
          @forelse($collections as $row)
          <tr>
            <td data-label="Date">{{ \Carbon\Carbon::parse($row->payment_date)->format('d M Y') }}</td>
            <td data-label="Client"><div class="font-semibold">{{ $row->client_name }}</div><div class="text-xs text-kc-charcoal/60">{{ $row->customer_code }}</div></td>
            <td data-label="Principal">R {{ number_format($row->principal_amount, 2) }}</td>
            <td data-label="Interest">R {{ number_format($row->interest_amount, 2) }}</td>
            <td data-label="Fees">R {{ number_format($row->fee_amount, 2) }}</td>
            <td data-label="Nu-Pay Fee" class="text-kc-charcoal/60">R {{ number_format($row->nupay_fee, 2) }}</td>
            <td data-label="Total" class="font-semibold text-emerald-600">R {{ number_format($row->payment_amount, 2) }}</td>
            <td data-label="Method"><span class="kc-badge kc-badge-silver">{{ ucfirst(str_replace('_',' ',$row->payment_method ?? '—')) }}</span></td>
            <td data-label="GL Ref" class="text-xs font-mono text-kc-charcoal/60">{{ $row->gl_batch_reference ?? '—' }}</td>
          </tr>
          @empty
          <tr><td colspan="9" class="text-center py-8 text-kc-charcoal/60">No collections in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $collections->links() }}</div>
  </div>
</x-app-layout>
