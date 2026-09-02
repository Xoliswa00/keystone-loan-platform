<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Disbursements Report</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="grid grid-cols-2 gap-4 mb-6">
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Disbursements</p><p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $totals->count ?? 0 }}</p></div>
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Total Disbursed</p><p class="font-display text-2xl font-bold text-kc-gold mt-1">R {{ number_format($totals->total ?? 0, 2) }}</p></div>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Date</th><th>Client</th><th>Type</th><th>Principal</th><th>Init Fee</th><th>Service Fee</th><th>Interest</th><th>Total Due</th><th>Term</th></tr></thead>
        <tbody>
          @forelse($disbursements as $row)
          <tr>
            <td data-label="Date">{{ \Carbon\Carbon::parse($row->disbursement_date)->format('d M Y') }}</td>
            <td data-label="Client"><div class="font-semibold">{{ $row->client_name }}</div><div class="text-xs text-kc-charcoal/40">{{ $row->customer_code }}</div></td>
            <td data-label="Type">{{ ucfirst($row->loan_type ?? '—') }}</td>
            <td data-label="Principal" class="font-semibold">R {{ number_format($row->disbursed_amount, 2) }}</td>
            <td data-label="Init Fee">R {{ number_format($row->initiation_fee ?? 0, 2) }}</td>
            <td data-label="Service Fee">R {{ number_format($row->service_fee ?? 0, 2) }}</td>
            <td data-label="Interest">R {{ number_format($row->interest_amount ?? 0, 2) }}</td>
            <td data-label="Total Due" class="font-semibold text-kc-gold">R {{ number_format($row->total_due ?? 0, 2) }}</td>
            <td data-label="Term">{{ $row->loan_term_months ?? 1 }} mo</td>
          </tr>
          @empty
          <tr><td colspan="9" class="text-center py-8 text-kc-charcoal/40">No disbursements in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $disbursements->links() }}</div>
  </div>
</x-app-layout>
