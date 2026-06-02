<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Balance Sheet</span>
    <p class="kc-page-subtitle">As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }}</p>
  </x-slot>

  <form method="GET" class="flex gap-3 mb-6">
    <div>
      <label class="kc-label">As at date</label>
      <input type="date" name="as_at_date" value="{{ $asAt }}" class="kc-input">
    </div>
    <div class="flex items-end">
      <button type="submit" class="kc-btn-primary">Apply</button>
    </div>
  </form>

  <div class="kc-card max-w-2xl">
    <h3 class="font-display text-lg font-semibold text-kc-navy mb-1">Keystone Capital Partners</h3>
    <p class="text-xs text-kc-charcoal/50 mb-6">Balance Sheet — As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }}</p>

    <table class="w-full text-sm">
      {{-- ASSETS --}}
      <thead>
        <tr class="border-b border-kc-gold/30">
          <th class="text-left py-2 font-semibold text-kc-charcoal/60">ASSETS</th>
          <th class="text-right py-2 font-semibold text-kc-charcoal/60">R</th>
        </tr>
      </thead>
      <tbody>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Cash at Bank</td>
          <td class="text-right py-2">{{ number_format($cashAtBank, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Loans Receivable (gross)</td>
          <td class="text-right py-2">{{ number_format($loansReceivableGross, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4 text-red-600">Less: Allowance for Credit Losses (IFRS 9 ECL)</td>
          <td class="text-right py-2 text-red-600">({{ number_format($allowanceForCreditLoss, 2) }})</td>
        </tr>
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2 pl-4">Loans Receivable (net)</td>
          <td class="text-right py-2">{{ number_format($loansReceivableGross - $allowanceForCreditLoss, 2) }}</td>
        </tr>
        <tr class="bg-kc-silver-light font-semibold">
          <td class="py-2 px-2">TOTAL ASSETS</td>
          <td class="text-right py-2 px-2">{{ number_format($cashAtBank + $loansReceivableGross - $allowanceForCreditLoss, 2) }}</td>
        </tr>
      </tbody>

      {{-- LIABILITIES --}}
      <thead>
        <tr class="border-b border-kc-gold/30 mt-4">
          <th class="text-left py-3 font-semibold text-kc-charcoal/60">LIABILITIES</th>
          <th class="text-right py-3"></th>
        </tr>
      </thead>
      <tbody>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Deferred Interest Income</td>
          <td class="text-right py-2">{{ number_format($deferredInterest, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Deferred Fee Income</td>
          <td class="text-right py-2">{{ number_format($deferredFees, 2) }}</td>
        </tr>
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">VAT Output Payable</td>
          <td class="text-right py-2">{{ number_format($vatOutput, 2) }}</td>
        </tr>
        <tr class="bg-kc-silver-light font-semibold">
          <td class="py-2 px-2">TOTAL LIABILITIES</td>
          <td class="text-right py-2 px-2">{{ number_format($deferredInterest + $deferredFees + $vatOutput, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 text-xs text-kc-charcoal/40">
      Prepared in accordance with IFRS 9 (Financial Instruments). ECL provisions based on Days Past Due staging.
    </div>
  </div>
</x-app-layout>
