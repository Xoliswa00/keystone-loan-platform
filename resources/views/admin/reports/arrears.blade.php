<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Arrears Report</span>
    <p class="kc-page-subtitle">IFRS 9 DPD buckets · As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">As at</label><input type="date" name="as_at_date" value="{{ $asAt }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  {{-- Bucket summary --}}
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    @foreach(['1-29 DPD','30-59 DPD','60-89 DPD','90-179 DPD','180+ DPD'] as $bucket)
    @php $b = $summary[$bucket] ?? null; @endphp
    <div class="kc-stat-card text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/70 mb-1">{{ $bucket }}</p>
      <p class="font-display text-lg font-bold text-kc-navy">{{ $b ? $b['count'] : 0 }}</p>
      <p class="text-xs text-red-600 font-semibold">R {{ number_format($b['total'] ?? 0, 2) }}</p>
    </div>
    @endforeach
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Client</th><th>Bucket</th><th>DPD</th><th>Loan Balance</th><th>Arrears</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @forelse($buckets as $row)
          <tr>
            <td data-label="Client">
              <div class="font-semibold">{{ $row->client_name }}</div>
              <div class="text-xs text-kc-charcoal/70">{{ $row->customer_code }}</div>
            </td>
            <td data-label="Bucket">
              @php $cls = str_contains($row->dpd_bucket,'180') || str_contains($row->dpd_bucket,'90') ? 'kc-badge-red' : (str_contains($row->dpd_bucket,'60') ? 'kc-badge-gold' : 'kc-badge-silver'); @endphp
              <span class="kc-badge {{ $cls }}">{{ $row->dpd_bucket }}</span>
            </td>
            <td data-label="DPD" class="font-bold {{ $row->days_past_due >= 90 ? 'text-red-600' : '' }}">{{ $row->days_past_due }}</td>
            <td data-label="Balance">R {{ number_format($row->remaining_balance, 2) }}</td>
            <td data-label="Arrears" class="font-semibold text-red-600">R {{ number_format($row->arrears_amount, 2) }}</td>
            <td data-label="Actions">
              <a href="{{ route('admin.collections') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Collections</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center py-8 text-kc-charcoal/70">No overdue accounts.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($buckets,'links')) <div class="mt-4">{{ $buckets->links() }}</div> @endif
  </div>
</x-app-layout>
