<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Income Statement</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex gap-3 mb-6">
    <div>
      <label class="kc-label">From</label>
      <input type="date" name="from_date" value="{{ $from }}" class="kc-input">
    </div>
    <div>
      <label class="kc-label">To</label>
      <input type="date" name="to_date" value="{{ $to }}" class="kc-input">
    </div>
    <div class="flex items-end">
      <button type="submit" class="kc-btn-primary">Apply</button>
    </div>
  </form>

  <div class="kc-card max-w-2xl">
    <h3 class="font-display text-lg font-semibold text-kc-navy mb-1">Keystone Capital Partners</h3>
    <p class="text-xs text-kc-charcoal/50 mb-6">Income Statement — {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-kc-gold/30">
          <th class="text-left py-2 font-semibold text-kc-charcoal/60">Income</th>
          <th class="text-right py-2 font-semibold text-kc-charcoal/60">R</th>
        </tr>
      </thead>
      <tbody>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Interest Income <span class="text-xs text-kc-charcoal/40">(VAT exempt — NCA)</span></td>
          <td class="text-right py-2">{{ number_format($interest_income, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Fee Income (excl. VAT)</td>
          <td class="text-right py-2">{{ number_format($fee_income_excl_vat, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Penalty Income</td>
          <td class="text-right py-2">{{ number_format($penalty_income, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2">Gross Income</td>
          <td class="text-right py-2">{{ number_format($gross_income, 2) }}</td>
        </tr>

        <tr>
          <td class="py-2 pt-4 font-semibold text-kc-charcoal/60" colspan="2">Expenses</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Credit Loss Expense — IFRS 9 ECL</td>
          <td class="text-right py-2 text-red-600">({{ number_format($credit_loss_expense, 2) }})</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Bank Charges (Nu-Pay collection fees)</td>
          <td class="text-right py-2 text-red-600">({{ number_format($bank_charges, 2) }})</td>
        </tr>
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2">Total Expenses</td>
          <td class="text-right py-2 text-red-600">({{ number_format($total_expenses, 2) }})</td>
        </tr>

        <tr class="bg-kc-navy text-white">
          <td class="py-3 px-4 font-display font-semibold">NET PROFIT / (LOSS)</td>
          <td class="text-right py-3 px-4 font-display font-semibold text-kc-gold">{{ number_format($net_profit, 2) }}</td>
        </tr>
      </tbody>
    </table>

    @if($deferred_interest > 0 || $deferred_fees > 0)
    <div class="mt-4 p-3 rounded border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/60">
      <strong>Note:</strong> Deferred interest of R{{ number_format($deferred_interest, 2) }} and deferred fees of R{{ number_format($deferred_fees, 2) }} relating to multi-month loans are carried as liabilities on the balance sheet and will be recognised when instalments are received.
    </div>
    @endif
  </div>
</x-app-layout>
