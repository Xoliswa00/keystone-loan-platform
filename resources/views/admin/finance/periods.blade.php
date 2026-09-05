<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Financial Periods</span>
    <p class="kc-page-subtitle">Accounting period management · open → closing → closed → locked</p>
  </x-slot>

  {{-- Status summary --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach(['open'=>'kc-badge-green','closing'=>'kc-badge-gold','closed'=>'kc-badge-navy','locked'=>'kc-badge-silver'] as $s=>$cls)
    <div class="kc-stat-card text-center">
      <span class="kc-badge {{ $cls }} mb-2">{{ ucfirst($s) }}</span>
      <p class="font-display text-2xl font-bold text-kc-navy">{{ $summary[$s] }}</p>
    </div>
    @endforeach
  </div>

  {{-- Open a period manually --}}
  <div class="flex justify-end mb-4" x-data="{open:false}">
    <button @click="open=!open" class="kc-btn-ghost text-sm">+ Open Period</button>
    <div x-show="open" @click.outside="open=false" x-transition
      class="absolute right-6 mt-10 w-64 bg-white rounded-xl shadow-kc-card border border-kc-silver-light p-4 z-50">
      <form method="POST" action="{{ route('admin.periods.store') }}" class="space-y-3">
        @csrf
        <div>
          <label class="kc-label">Period (YYYY-MM)</label>
          <input type="text" name="period" pattern="\d{4}-\d{2}" placeholder="{{ now()->addMonth()->format('Y-m') }}"
            class="kc-input font-mono text-sm" required>
        </div>
        <button type="submit" class="kc-btn-primary w-full justify-center text-sm">Open</button>
      </form>
    </div>
  </div>

  {{-- Periods table --}}
  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Period</th><th>Status</th><th>Checklist</th>
          <th>Income</th><th>Expenses</th><th>Net Profit</th>
          <th>Year-End</th><th>Closed</th><th></th>
        </tr></thead>
        <tbody>
          @foreach($periods as $fp)
          @php $sc = \App\Models\FinancialPeriod::STATUS_CLASSES[$fp->status] ?? 'kc-badge-silver'; @endphp
          <tr class="{{ $fp->period === now()->format('Y-m') ? 'bg-kc-gold/5' : '' }}">
            <td data-label="Period">
              <div class="font-semibold text-kc-navy">{{ $fp->displayLabel() }}</div>
              @if($fp->period === now()->format('Y-m'))
                <div class="text-[10px] text-kc-gold-muted font-semibold uppercase tracking-wide">Current</div>
              @endif
            </td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst($fp->status) }}</span></td>
            <td data-label="Checklist">
              <div class="flex items-center gap-1.5">
                <div class="w-16 h-1 bg-kc-silver-light rounded-full overflow-hidden">
                  <div class="h-full bg-kc-gold rounded-full" style="width:{{ $fp->checklistProgress() }}%"></div>
                </div>
                <span class="text-xs {{ $fp->checklistComplete() ? 'text-emerald-600 font-semibold' : 'text-kc-charcoal/70' }}">
                  {{ $fp->checklistProgress() }}%
                </span>
              </div>
            </td>
            <td data-label="Income" class="text-emerald-600 font-semibold text-sm">
              {{ $fp->period_income !== null ? 'R '.number_format($fp->period_income,2) : '—' }}
            </td>
            <td data-label="Expenses" class="text-red-500 text-sm">
              {{ $fp->period_expenses !== null ? 'R '.number_format($fp->period_expenses,2) : '—' }}
            </td>
            <td data-label="Net" class="font-bold {{ ($fp->period_net_profit ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
              {{ $fp->period_net_profit !== null ? 'R '.number_format($fp->period_net_profit,2) : '—' }}
            </td>
            <td data-label="Year-End">
              @if($fp->is_year_end)
                <span class="kc-badge {{ $fp->year_end_complete ? 'kc-badge-green' : 'kc-badge-gold' }} text-[10px]">
                  {{ $fp->year_end_complete ? 'Complete' : 'Pending' }}
                </span>
              @else
                <span class="text-kc-charcoal/70 text-xs">—</span>
              @endif
            </td>
            <td data-label="Closed" class="text-xs text-kc-charcoal/70">
              {{ $fp->closed_at?->format('d M Y') ?? '—' }}
            </td>
            <td data-label="">
              <a href="{{ route('admin.periods.show', $fp) }}"
                class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">
                {{ $fp->isOpen() || $fp->isClosing() ? 'Manage →' : 'View →' }}
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $periods->links() }}</div>
  </div>
</x-app-layout>
