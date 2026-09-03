<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Balance Sheet</span>
    <p class="kc-page-subtitle">As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
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
          <td class="text-right py-2 px-2">{{ number_format($totalAssets, 2) }}</td>
        </tr>
      </tbody>

      {{-- EQUITY --}}
      <thead>
        <tr class="border-b border-kc-gold/30 mt-4">
          <th class="text-left py-3 font-semibold text-kc-charcoal/60">EQUITY</th>
          <th class="text-right py-3"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($equityGroups as $groupName => $lines)
          @foreach($lines as $line)
          <tr class="border-b border-kc-silver-light">
            <td class="py-2 pl-4">{{ $line->label }}</td>
            <td class="text-right py-2">{{ number_format($line->balance, 2) }}</td>
          </tr>
          @endforeach
        @empty
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4 text-kc-charcoal/60" colspan="2">No equity accounts configured.</td>
        </tr>
        @endforelse
        {{-- Unclosed net income sitting in Income/Expense accounts — only
             swept into Retained Earnings at a formal year-end close, so an
             interim statement needs this to actually balance. --}}
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4">Current Period Earnings <span class="text-xs text-kc-charcoal/60">(unaudited, pre-close)</span></td>
          <td class="text-right py-2">{{ number_format($currentPeriodEarnings, 2) }}</td>
        </tr>
        <tr class="bg-kc-silver-light font-semibold">
          <td class="py-2 px-2">TOTAL EQUITY</td>
          <td class="text-right py-2 px-2">{{ number_format($totalEquity, 2) }}</td>
        </tr>
      </tbody>

      {{-- LIABILITIES — sub-grouped by account_group (Current Liability,
           Long-term Liability, Shareholder Loans, ...) per IFRS presentation --}}
      <thead>
        <tr class="border-b border-kc-gold/30 mt-4">
          <th class="text-left py-3 font-semibold text-kc-charcoal/60">LIABILITIES</th>
          <th class="text-right py-3"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($liabilityGroups as $groupName => $lines)
        <tr>
          <td class="pt-3 pb-1 pl-2 text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50" colspan="2">{{ $groupName }}</td>
        </tr>
        @foreach($lines as $line)
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-6">{{ $line->label }}</td>
          <td class="text-right py-2">{{ number_format($line->balance, 2) }}</td>
        </tr>
        @endforeach
        <tr class="text-xs text-kc-charcoal/50">
          <td class="py-1 pl-6">Subtotal</td>
          <td class="text-right py-1">{{ number_format($lines->sum('balance'), 2) }}</td>
        </tr>
        @empty
        <tr class="border-b border-kc-silver-light">
          <td class="py-2 pl-4 text-kc-charcoal/60" colspan="2">No liability accounts configured.</td>
        </tr>
        @endforelse
        <tr class="bg-kc-silver-light font-semibold">
          <td class="py-2 px-2">TOTAL LIABILITIES</td>
          <td class="text-right py-2 px-2">{{ number_format($totalLiabilities, 2) }}</td>
        </tr>
      </tbody>

      {{-- BALANCE CHECK — Assets must equal Equity + Liabilities. A mismatch
           here means a real GL posting problem, not a report bug — every
           figure above is a live sum, so this is the same check an
           accountant would run on a trial balance. --}}
      @php $equityPlusLiab = $totalEquity + $totalLiabilities; $balanced = round($totalAssets - $equityPlusLiab, 2) === 0.0; @endphp
      <tfoot>
        <tr class="border-t-2 {{ $balanced ? 'border-emerald-400' : 'border-red-400' }} font-bold">
          <td class="py-3 px-2">TOTAL EQUITY + LIABILITIES</td>
          <td class="text-right py-3 px-2 {{ $balanced ? 'text-emerald-600' : 'text-red-600' }}">
            {{ number_format($equityPlusLiab, 2) }}
            @unless($balanced)
              <span class="block text-xs font-normal">⚠ Does not match Total Assets (R{{ number_format($totalAssets, 2) }}) — variance R{{ number_format($totalAssets - $equityPlusLiab, 2) }}</span>
            @endunless
          </td>
        </tr>
      </tfoot>
    </table>

    <div class="mt-4 text-xs text-kc-charcoal/60">
      Prepared in accordance with IFRS 9 (Financial Instruments). ECL provisions based on Days Past Due staging.
    </div>
  </div>
</x-app-layout>
