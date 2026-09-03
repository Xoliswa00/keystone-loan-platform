<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Income Statement</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
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
      {{-- Net Interest Income — industry-standard presentation (per Capitec/
           FirstRand audited financials): interest expense is netted directly
           against interest income, not shown as "cost of sales" or lumped
           into operating expenses. --}}
      <tbody>
        @foreach($interestIncomeLines->flatten() as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-2">{{ $line->label }}</td>
          <td class="text-right py-2">{{ number_format($line->amount, 2) }}</td>
        </tr>
        @endforeach
        @foreach($interestExpenseLines->flatten() as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-2">{{ $line->label }}</td>
          <td class="text-right py-2 text-red-600">({{ number_format($line->amount, 2) }})</td>
        </tr>
        @endforeach
        <tr class="border-b border-kc-navy/20 font-semibold bg-kc-silver-light/40">
          <td class="py-2 px-2">NET INTEREST INCOME</td>
          <td class="text-right py-2 px-2">{{ number_format($netInterestIncome, 2) }}</td>
        </tr>

        @foreach($creditLossLines->flatten() as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-2">{{ $line->label }}</td>
          <td class="text-right py-2 text-red-600">({{ number_format($line->amount, 2) }})</td>
        </tr>
        @endforeach
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2 px-2">Net Interest Income After Credit Losses</td>
          <td class="text-right py-2 px-2">{{ number_format($netInterestIncomeAfterCreditLosses, 2) }}</td>
        </tr>
      </tbody>

      {{-- Non-interest income --}}
      <thead>
        <tr>
          <th class="text-left pt-4 pb-2 font-semibold text-kc-charcoal/60">Non-Interest Income</th>
          <th class="text-right"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($nonInterestIncomeGroups as $groupName => $lines)
        <tr>
          <td class="pt-2 pb-1 pl-2 text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50" colspan="2">{{ $groupName }}</td>
        </tr>
        @foreach($lines as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-6">{{ $line->label }}</td>
          <td class="text-right py-2">{{ number_format($line->amount, 2) }}</td>
        </tr>
        @endforeach
        @empty
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4 text-kc-charcoal/60" colspan="2">No non-interest income posted for this period.</td>
        </tr>
        @endforelse
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2">Total Income</td>
          <td class="text-right py-2">{{ number_format($totalIncome, 2) }}</td>
        </tr>
      </tbody>

      {{-- Operating expenses --}}
      <thead>
        <tr>
          <th class="text-left pt-4 pb-2 font-semibold text-kc-charcoal/60">Operating Expenses</th>
          <th class="text-right"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($operatingExpenseGroups as $groupName => $lines)
        <tr>
          <td class="pt-2 pb-1 pl-2 text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50" colspan="2">{{ $groupName }}</td>
        </tr>
        @foreach($lines as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-6">{{ $line->label }}</td>
          <td class="text-right py-2 text-red-600">({{ number_format($line->amount, 2) }})</td>
        </tr>
        @endforeach
        @empty
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4 text-kc-charcoal/60" colspan="2">No operating expenses posted for this period.</td>
        </tr>
        @endforelse
        <tr class="border-b border-kc-navy/20 font-semibold">
          <td class="py-2">Total Operating Expenses</td>
          <td class="text-right py-2 text-red-600">({{ number_format($operatingExpenses, 2) }})</td>
        </tr>

        <tr class="bg-kc-navy text-white">
          <td class="py-3 px-4 font-display font-semibold">NET PROFIT / (LOSS)</td>
          <td class="text-right py-3 px-4 font-display font-semibold text-kc-gold">{{ number_format($netProfit, 2) }}</td>
        </tr>
      </tbody>
    </table>

    @if($deferredInterest > 0 || $deferredFees > 0)
    <div class="mt-4 p-3 rounded border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/60">
      <strong>Note:</strong> Deferred interest of R{{ number_format($deferredInterest, 2) }} and deferred fees of R{{ number_format($deferredFees, 2) }} relating to multi-month loans are carried as liabilities on the balance sheet and will be recognised when instalments are received.
    </div>
    @endif
  </div>
</x-app-layout>
