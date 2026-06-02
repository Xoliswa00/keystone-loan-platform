<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Reversal Audit</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="kc-card">
    @if($reversals->isEmpty())
      <p class="text-center text-kc-charcoal/40 py-8">No reversals in this period.</p>
    @else
      <table class="kc-table">
        <thead><tr><th>Date</th><th>Client</th><th>Loan</th><th>Amount Reversed</th><th>Reference</th><th>GL Batch</th></tr></thead>
        <tbody>
          @foreach($reversals as $r)
          <tr>
            <td data-label="Date">{{ \Carbon\Carbon::parse($r->payment_date)->format('d M Y') }}</td>
            <td data-label="Client">{{ $r->client_name }}</td>
            <td data-label="Loan" class="font-mono text-xs">#{{ str_pad($r->loan_id, 6, '0', STR_PAD_LEFT) }}</td>
            <td data-label="Amount" class="font-semibold text-red-600">R {{ number_format(abs($r->payment_amount), 2) }}</td>
            <td data-label="Reference" class="text-xs">{{ $r->payment_reference ?? '—' }}</td>
            <td data-label="GL Batch" class="font-mono text-xs text-kc-charcoal/40">{{ $r->gl_batch_reference }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</x-app-layout>
