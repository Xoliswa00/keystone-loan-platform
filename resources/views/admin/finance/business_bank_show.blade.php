<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Business Bank Statement: {{ $batch->import_ref }}</span>
    <p class="kc-page-subtitle">{{ $meta['bank_name'] ?? '' }} · {{ $meta['account_name'] ?? '' }} · {{ $meta['period'] ?? '' }}</p>
  </x-slot>

  {{-- 3-way reconciliation summary --}}
  @if(!empty($reconciliation))
  <div class="kc-card mb-6">
    <h4 class="font-display font-semibold text-kc-navy mb-4">3-Way Reconciliation</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div>
        <h5 class="text-xs font-semibold text-kc-charcoal/60 uppercase tracking-wider mb-3">Collections (Credits)</h5>
        <table class="w-full text-sm">
          <tr class="border-b border-kc-silver-light"><td class="py-2 text-kc-charcoal/60">Bank Credits</td><td class="text-right font-semibold">R {{ number_format($reconciliation['bank_credits'],2) }}</td></tr>
          <tr class="border-b border-kc-silver-light"><td class="py-2 text-kc-charcoal/60">Nu-Pay Remitted</td><td class="text-right font-semibold">R {{ number_format($reconciliation['nupay_total'],2) }}</td></tr>
          <tr class="{{ abs($reconciliation['collections_variance']) > 1 ? 'bg-red-50' : '' }}">
            <td class="py-2 font-semibold">Variance</td>
            <td class="text-right font-bold {{ abs($reconciliation['collections_variance']) > 1 ? 'text-red-600' : 'text-emerald-600' }}">
              R {{ number_format(abs($reconciliation['collections_variance']),2) }}
              {{ $reconciliation['collections_variance'] == 0 ? '✓ Reconciled' : ($reconciliation['collections_variance'] > 0 ? '(bank excess)' : '(bank short)') }}
            </td>
          </tr>
        </table>
      </div>
      <div>
        <h5 class="text-xs font-semibold text-kc-charcoal/60 uppercase tracking-wider mb-3">Disbursements (Debits)</h5>
        <table class="w-full text-sm">
          <tr class="border-b border-kc-silver-light"><td class="py-2 text-kc-charcoal/60">Bank Debits</td><td class="text-right font-semibold">R {{ number_format($reconciliation['bank_debits'],2) }}</td></tr>
          <tr class="border-b border-kc-silver-light"><td class="py-2 text-kc-charcoal/60">System Disbursements</td><td class="text-right font-semibold">R {{ number_format($reconciliation['disb_total'],2) }}</td></tr>
          <tr class="{{ abs($reconciliation['disbursement_variance']) > 1 ? 'bg-red-50' : '' }}">
            <td class="py-2 font-semibold">Variance</td>
            <td class="text-right font-bold {{ abs($reconciliation['disbursement_variance']) > 1 ? 'text-red-600' : 'text-emerald-600' }}">
              R {{ number_format(abs($reconciliation['disbursement_variance']),2) }}
              {{ $reconciliation['disbursement_variance'] == 0 ? '✓ Reconciled' : '' }}
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  @endif

  {{-- Match stats + action --}}
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Total</p><p class="font-display text-xl font-bold text-kc-navy mt-1">{{ $summary->total }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Credits</p><p class="font-display text-xl font-bold text-emerald-600 mt-1">R {{ number_format($summary->total_credits,2) }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Debits</p><p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($summary->total_debits,2) }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Matched</p><p class="font-display text-xl font-bold text-emerald-600 mt-1">{{ $summary->matched }}</p></div>
    <div class="kc-stat-card text-center"><p class="text-xs text-kc-charcoal/60 uppercase">Exceptions</p><p class="font-display text-xl font-bold {{ $summary->exceptions > 0 ? 'text-red-600' : 'text-kc-navy' }} mt-1">{{ $summary->exceptions }}</p></div>
  </div>

  <div class="flex gap-3 justify-end mb-4">
    <form method="POST" action="{{ route('admin.finance.business-bank.reconcile', $batch->id) }}">
      @csrf
      <button type="submit" class="kc-btn-ghost">Quick Auto-Match</button>
    </form>
    <a href="{{ route('admin.finance.recon.workspace', $batch->id) }}" class="kc-btn-primary">
      Open Reconciliation Workspace →
    </a>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Match</th><th>Note</th></tr></thead>
        <tbody>
          @foreach($lines as $line)
          @php $msc = match($line->match_status){'matched'=>'kc-badge-green','exception'=>'kc-badge-red',default=>'kc-badge-silver'}; @endphp
          <tr class="{{ $line->match_status === 'exception' ? 'bg-red-50' : '' }}">
            <td data-label="Date" class="text-xs">{{ $line->transaction_date }}</td>
            <td data-label="Description" class="text-xs max-w-xs">{{ Str::limit($line->description,60) }}</td>
            <td data-label="Debit" class="text-red-600">{{ $line->debit_amount > 0 ? 'R '.number_format($line->debit_amount,2) : '—' }}</td>
            <td data-label="Credit" class="text-emerald-600">{{ $line->credit_amount > 0 ? 'R '.number_format($line->credit_amount,2) : '—' }}</td>
            <td data-label="Balance" class="text-xs">{{ $line->running_balance ? 'R '.number_format($line->running_balance,2) : '—' }}</td>
            <td data-label="Match"><span class="kc-badge {{ $msc }}">{{ ucfirst($line->match_status) }}</span></td>
            <td data-label="Note" class="text-xs text-kc-charcoal/60">{{ Str::limit($line->match_note ?? '',50) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $lines->links() }}</div>
  </div>
</x-app-layout>
