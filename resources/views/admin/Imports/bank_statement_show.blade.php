<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Bank Statement: {{ $batch->import_ref }}</span>
    <p class="kc-page-subtitle">{{ $batch->row_count }} transactions · {{ $batch->status }}</p>
  </x-slot>

  {{-- Summary --}}
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Total</p><p class="font-display text-xl font-bold text-kc-navy mt-1">{{ $summary->total }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Credits</p><p class="font-display text-xl font-bold text-emerald-600 mt-1">R {{ number_format($summary->total_credits,2) }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Debits</p><p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($summary->total_debits,2) }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Matched</p><p class="font-display text-xl font-bold text-emerald-600 mt-1">{{ $summary->matched }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Exceptions</p><p class="font-display text-xl font-bold {{ $summary->exceptions > 0 ? 'text-red-600' : 'text-kc-navy' }} mt-1">{{ $summary->exceptions }}</p></div>
  </div>

  <div class="flex justify-end mb-4">
    <form method="POST" action="{{ route('bank-statement.reconcile', $batch->id) }}">
      @csrf
      <button type="submit" class="kc-btn-primary">Auto-Reconcile Against Nu-Pay</button>
    </form>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Match</th><th>Note</th></tr></thead>
        <tbody>
          @foreach($lines as $line)
          @php $msc = match($line->match_status){'matched'=>'kc-badge-green','exception'=>'kc-badge-red',default=>'kc-badge-silver'}; @endphp
          <tr>
            <td data-label="Date" class="text-xs">{{ $line->transaction_date }}</td>
            <td data-label="Desc" class="text-xs max-w-xs truncate">{{ $line->description }}</td>
            <td data-label="Debit" class="text-red-600">{{ $line->debit_amount > 0 ? 'R '.number_format($line->debit_amount,2) : '—' }}</td>
            <td data-label="Credit" class="text-emerald-600">{{ $line->credit_amount > 0 ? 'R '.number_format($line->credit_amount,2) : '—' }}</td>
            <td data-label="Balance" class="text-xs">{{ $line->running_balance ? 'R '.number_format($line->running_balance,2) : '—' }}</td>
            <td data-label="Match"><span class="kc-badge {{ $msc }}">{{ ucfirst($line->match_status) }}</span></td>
            <td data-label="Note" class="text-xs text-kc-charcoal/60">{{ Str::limit($line->match_note ?? '',40) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $lines->links() }}</div>
  </div>
</x-app-layout>
