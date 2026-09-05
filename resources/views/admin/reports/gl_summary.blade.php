<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Trial Balance / GL Summary</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Total Debits</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($totalDebits ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Total Credits</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($totalCredits ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Balanced</p>
      <p class="font-display text-2xl font-bold {{ ($balanced ?? true) ? 'text-emerald-600' : 'text-red-600' }} mt-1">
        {{ ($balanced ?? true) ? 'Yes' : 'No' }}
      </p>
    </div>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Account Code</th><th>Type</th><th>Category</th><th>Total Debit</th><th>Total Credit</th><th>Net Balance</th>
        </tr></thead>
        <tbody>
          @forelse($accounts as $acct)
          <tr>
            <td data-label="Code" class="font-mono font-semibold">{{ $acct->account_code }}</td>
            <td data-label="Type" class="text-xs"><span class="kc-badge kc-badge-silver">{{ ucfirst($acct->account_type) }}</span></td>
            <td data-label="Category" class="text-xs text-kc-charcoal/70">{{ ucfirst($acct->account_category) }}</td>
            <td data-label="Debit">R {{ number_format($acct->total_debit, 2) }}</td>
            <td data-label="Credit">R {{ number_format($acct->total_credit, 2) }}</td>
            <td data-label="Net" class="font-semibold {{ $acct->net_balance > 0 ? 'text-kc-navy' : 'text-red-600' }}">
              R {{ number_format($acct->net_balance, 2) }}
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center py-8 text-kc-charcoal/70">No GL entries in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
