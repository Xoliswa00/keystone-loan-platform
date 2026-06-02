<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Write-Off Register</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="kc-card mb-4">
    <p class="text-sm text-kc-charcoal/60">Total written off: <span class="font-display text-xl font-semibold text-red-600">R {{ number_format($totalWrittenOff, 2) }}</span></p>
  </div>

  <div class="kc-card">
    <table class="kc-table">
      <thead><tr>
        <th>Loan</th><th>Client</th><th>Write-Off Date</th><th>Amount</th><th>Reason</th><th>By</th>
      </tr></thead>
      <tbody>
        @forelse($writeOffs as $loan)
        <tr>
          <td class="font-mono text-xs">#{{ str_pad($loan->id, 6, '0', STR_PAD_LEFT) }}</td>
          <td>{{ $loan->user?->name ?? '—' }}<br><span class="text-xs text-kc-charcoal/40">{{ $loan->user?->customer?->customer_code }}</span></td>
          <td>{{ \Carbon\Carbon::parse($loan->written_off_date)->format('d M Y') }}</td>
          <td class="text-red-600 font-semibold">R {{ number_format($loan->write_off_amount, 2) }}</td>
          <td class="text-xs">{{ $loan->write_off_reason ?? '—' }}</td>
          <td class="text-xs">{{ $loan->writtenOffBy?->name ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-kc-charcoal/40 py-8">No write-offs in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-app-layout>
