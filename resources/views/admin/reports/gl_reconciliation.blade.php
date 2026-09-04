<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">GL Reconciliation</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  @if($summary)
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Total Debits</p><p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($summary->total_debit ?? 0, 2) }}</p></div>
    <div class="kc-stat-card"><p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Total Credits</p><p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($summary->total_credit ?? 0, 2) }}</p></div>
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Unbalanced Batches</p>
      <p class="font-display text-xl font-bold {{ $batches->count() > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ $batches->count() }}</p>
    </div>
  </div>
  @endif

  @if($batches->isEmpty())
    <div class="kc-alert-success mb-4">All GL batches in this period are balanced.</div>
  @else
    <div class="kc-alert-error mb-4">{{ $batches->count() }} unbalanced batch(es) found — review immediately.</div>
    <div class="kc-card mb-6">
      <table class="kc-table">
        <thead><tr><th>Batch ID</th><th>Reference</th><th>Source</th><th>Posted</th><th>Total Debit</th><th>Total Credit</th><th>Variance</th></tr></thead>
        <tbody>
          @foreach($batches as $b)
          <tr>
            <td data-label="Batch">#{{ $b->id }}</td>
            <td data-label="Reference" class="font-mono text-xs">{{ $b->reference }}</td>
            <td data-label="Source" class="text-xs">{{ class_basename($b->source_type ?? '—') }}</td>
            <td data-label="Posted">{{ \Carbon\Carbon::parse($b->posted_at)->format('d M Y H:i') }}</td>
            <td data-label="Debit">R {{ number_format($b->total_debit, 2) }}</td>
            <td data-label="Credit">R {{ number_format($b->total_credit, 2) }}</td>
            <td data-label="Variance" class="font-bold text-red-600">R {{ number_format($b->variance, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  {{-- Point-in-time snapshot checks — same as keystone:reconcile-gl checks 2 & 3 --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div>
      @if($loanReceivable['variance'] > 1.00)
        <div class="kc-alert-error">
          <p class="font-semibold">Loan Receivable variance: R {{ number_format($loanReceivable['variance'], 2) }}</p>
          <p class="text-xs mt-1">Loan ledger total: R {{ number_format($loanReceivable['ledger_total'], 2) }} · GL account 1200: R {{ number_format($loanReceivable['gl_total'], 2) }}</p>
        </div>
      @else
        <div class="kc-alert-success">Loan ledger matches GL Loans Receivable (account 1200).</div>
      @endif
    </div>
    <div>
      @if($deferredInterest['variance'] > 1.00)
        <div class="kc-alert-error">
          <p class="font-semibold">Deferred Interest variance: R {{ number_format($deferredInterest['variance'], 2) }}</p>
          <p class="text-xs mt-1">Ledger total: R {{ number_format($deferredInterest['ledger_total'], 2) }} · GL account 2100: R {{ number_format($deferredInterest['gl_total'], 2) }}</p>
        </div>
      @else
        <div class="kc-alert-success">Deferred interest matches GL account 2100.</div>
      @endif
    </div>
  </div>
</x-app-layout>
