<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">{{ $period->displayLabel() }}</span>
    <p class="kc-page-subtitle">Financial period</p>
  </x-slot>

  @php $sc = \App\Models\FinancialPeriod::STATUS_CLASSES[$period->status] ?? 'kc-badge-silver'; @endphp
  <div class="flex flex-wrap items-center gap-2 mb-6">
    <span class="kc-badge {{ $sc }}">{{ ucfirst($period->status) }}</span>
    @if($period->is_year_end)
      <span class="kc-badge kc-badge-gold">Year-End</span>
    @endif
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- LEFT: Period detail + actions ────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- P&L summary --}}
      @if($period->period_net_profit !== null)
      <div class="kc-card-navy">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
          <div>
            <p class="text-white/70 text-xs uppercase tracking-wider">Income</p>
            <p class="font-display text-xl font-bold text-emerald-400 mt-1">R {{ number_format($period->period_income,2) }}</p>
          </div>
          <div>
            <p class="text-white/70 text-xs uppercase tracking-wider">Expenses</p>
            <p class="font-display text-xl font-bold text-red-400 mt-1">R {{ number_format($period->period_expenses,2) }}</p>
          </div>
          <div>
            <p class="text-white/70 text-xs uppercase tracking-wider">Net Profit</p>
            <p class="font-display text-xl font-bold {{ $period->period_net_profit >= 0 ? 'text-kc-gold' : 'text-red-400' }} mt-1">
              R {{ number_format($period->period_net_profit,2) }}
            </p>
          </div>
        </div>
      </div>
      @endif

      {{-- Pre-close checklist --}}
      <div class="kc-card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-display font-semibold text-kc-navy">Pre-Close Checklist</h4>
          <span class="text-sm font-semibold {{ $period->checklistComplete() ? 'text-emerald-600' : 'text-kc-charcoal/70' }}">
            {{ $period->checklistProgress() }}% complete
          </span>
        </div>
        <div class="w-full h-1.5 bg-kc-silver-light rounded-full overflow-hidden mb-5">
          <div class="h-full bg-kc-gold rounded-full transition-all" style="width:{{ $period->checklistProgress() }}%"></div>
        </div>

        <div class="space-y-3">

          {{-- 1. Provisioning --}}
          <div class="flex items-center justify-between p-3 rounded-lg border {{ $period->provisioning_complete ? 'border-emerald-200 bg-emerald-50' : 'border-kc-silver-light' }}">
            <div class="flex items-center gap-3">
              @if($period->provisioning_complete)
                <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
              @else
                <div class="w-6 h-6 rounded-full border-2 border-kc-silver flex-shrink-0"></div>
              @endif
              <div>
                <p class="text-sm font-semibold">IFRS 9 Provisioning</p>
                <p class="text-xs text-kc-charcoal/70">Run ECL provision for all active loans</p>
              </div>
            </div>
            @if(!$period->provisioning_complete && $period->canPost())
            <form method="POST" action="{{ route('admin.periods.provisioning', $period) }}">
              @csrf
              <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Run</button>
            </form>
            @endif
          </div>

          {{-- 2. Facility interest --}}
          <div class="flex items-center justify-between p-3 rounded-lg border {{ $period->facility_interest_accrued ? 'border-emerald-200 bg-emerald-50' : 'border-kc-silver-light' }}">
            <div class="flex items-center gap-3">
              @if($period->facility_interest_accrued)
                <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
              @else
                <div class="w-6 h-6 rounded-full border-2 border-kc-silver flex-shrink-0"></div>
              @endif
              <div>
                <p class="text-sm font-semibold">Funding Facility Interest Accrual</p>
                <p class="text-xs text-kc-charcoal/70">Accrue interest on all active funding facilities</p>
              </div>
            </div>
            @if(!$period->facility_interest_accrued && $period->canPost())
            <form method="POST" action="{{ route('admin.periods.facility-interest', $period) }}">
              @csrf
              <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Run</button>
            </form>
            @endif
          </div>

          {{-- 3. Bank reconciliation --}}
          <div class="flex items-center justify-between p-3 rounded-lg border {{ $period->bank_recon_complete ? 'border-emerald-200 bg-emerald-50' : 'border-kc-silver-light' }}">
            <div class="flex items-center gap-3">
              @if($period->bank_recon_complete)
                <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
              @else
                <div class="w-6 h-6 rounded-full border-2 border-kc-silver flex-shrink-0"></div>
              @endif
              <div>
                <p class="text-sm font-semibold">Bank Reconciliation</p>
                <p class="text-xs text-kc-charcoal/70">
                  @if($period->bank_recon_no_activity)
                    Confirmed no bank activity for this period
                  @else
                    All bank lines matched and GL balance agrees with statement
                  @endif
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <a href="{{ route('admin.finance.business-bank.upload') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Bank Recon →</a>
              @if(!$period->bank_recon_complete && $period->canPost())
              <form method="POST" action="{{ route('admin.periods.bank-recon', $period) }}">
                @csrf
                <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Mark Done</button>
              </form>
              <form method="POST" action="{{ route('admin.periods.bank-recon-no-activity', $period) }}"
                onsubmit="return confirm('Confirm there was genuinely no bank activity for {{ $period->displayLabel() }}? The system will check for repayments, disbursements, and uploaded bank statements before accepting this.')">
                @csrf
                <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">No Activity</button>
              </form>
              @endif
            </div>
          </div>

          {{-- 4. Trial balance --}}
          <div class="flex items-center justify-between p-3 rounded-lg border {{ $period->trial_balance_generated ? 'border-emerald-200 bg-emerald-50' : 'border-kc-silver-light' }}">
            <div class="flex items-center gap-3">
              @if($period->trial_balance_generated)
                <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
              @else
                <div class="w-6 h-6 rounded-full border-2 border-kc-silver flex-shrink-0"></div>
              @endif
              <div>
                <p class="text-sm font-semibold">Trial Balance &amp; P&amp;L Snapshot</p>
                <p class="text-xs text-kc-charcoal/70">Capture GL account balances at period-end</p>
              </div>
            </div>
            @if(!$period->trial_balance_generated && $period->canPost())
            <form method="POST" action="{{ route('admin.periods.trial-balance', $period) }}">
              @csrf
              <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Generate</button>
            </form>
            @endif
          </div>
        </div>
      </div>

      {{-- GL balance snapshot --}}
      @if($period->gl_closing_balances)
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">GL Closing Balances Snapshot</h4>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Account Code</th><th class="text-right">Balance at Close</th></tr></thead>
            <tbody>
              @foreach($period->gl_closing_balances as $code => $bal)
              @if(abs($bal) > 0)
              <tr>
                <td data-label="Account" class="font-mono text-xs">{{ $code }}</td>
                <td data-label="Balance" class="text-right font-semibold {{ $bal < 0 ? 'text-red-600' : '' }}">
                  R {{ number_format($bal, 2) }}
                </td>
              </tr>
              @endif
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>

    {{-- RIGHT: Actions ───────────────────────────────────────────────────── --}}
    <div class="space-y-5">

      {{-- Period lifecycle actions --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-4">Period Actions</h5>

        @if($period->isOpen())
        <form method="POST" action="{{ route('admin.periods.start-close', $period) }}" class="mb-3">
          @csrf
          <button type="submit" class="kc-btn-primary w-full justify-center">
            Start Period-End Close
          </button>
        </form>
        <p class="text-xs text-kc-charcoal/70">Moves period to 'Closing'. You can still post adjusting entries.</p>
        @endif

        @if($period->isClosing())
        <div class="kc-alert-warning mb-3 text-xs">
          Period is closing. Complete the checklist on the left, then close.
        </div>
        <form method="POST" action="{{ route('admin.periods.close', $period) }}" class="mb-3">
          @csrf
          <button type="submit"
            class="{{ $period->checklistComplete() ? 'kc-btn-primary' : 'kc-btn-ghost opacity-50 cursor-not-allowed' }} w-full justify-center"
            {{ $period->checklistComplete() ? '' : 'disabled' }}
            onclick="return confirm('Close {{ $period->displayLabel() }}? {{ $period->is_year_end ? "This is a year-end period — year-end journals will be posted." : "" }}')">
            Close Period {{ $period->is_year_end ? '+ Year-End' : '' }}
          </button>
        </form>
        @if(!$period->checklistComplete())
        <p class="text-xs text-red-600">Complete all 4 checklist items to enable close.</p>
        @endif
        @endif

        @if($period->status === 'closed')
        <div class="kc-alert-success mb-3 text-xs">
          Period closed on {{ $period->closed_at?->format('d M Y') }} by {{ $period->closedBy?->name ?? 'System' }}.
        </div>
        <form method="POST" action="{{ route('admin.periods.lock', $period) }}">
          @csrf
          <button type="submit" class="kc-btn-secondary w-full justify-center"
            onclick="return confirm('Lock {{ $period->displayLabel() }}? This is irreversible — no further changes will be allowed.')">
            Lock Period (Final Sign-off)
          </button>
        </form>
        @endif

        @if($period->isLocked())
        <div class="flex items-center gap-2 p-3 rounded-lg border border-kc-silver-light bg-kc-silver-light/30">
          <svg class="w-4 h-4 text-kc-charcoal/70 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
          </svg>
          <div>
            <p class="text-xs font-semibold text-kc-charcoal/70">Period Locked</p>
            <p class="text-[10px] text-kc-charcoal/70">Locked {{ $period->locked_at?->format('d M Y') }} by {{ $period->lockedBy?->name ?? 'System' }}</p>
          </div>
        </div>
        @endif
      </div>

      {{-- Year-end info --}}
      @if($period->is_year_end)
      <div class="kc-card border-l-4 border-kc-gold">
        <h5 class="font-semibold text-kc-navy mb-2">Year-End ({{ $period->fiscal_year }})</h5>
        <p class="text-xs text-kc-charcoal/70 mb-3">
          When this period closes, a year-end journal is automatically posted:
        </p>
        <ul class="text-xs text-kc-charcoal/70 space-y-1 list-disc list-inside">
          <li>All income accounts cleared → Retained Earnings</li>
          <li>All expense accounts cleared → Retained Earnings</li>
          <li>Net profit/loss transferred to equity</li>
          <li>New fiscal year opens with zero income/expense balances</li>
        </ul>
        @if($period->year_end_complete)
        <div class="mt-3">
          <span class="kc-badge kc-badge-green">Year-end complete</span>
          <p class="text-xs text-kc-charcoal/70 mt-1">GL ref: {{ $period->year_end_gl_batch_ref }}</p>
        </div>
        @endif
      </div>
      @endif

      {{-- Period metadata --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Period Info</h5>
        <table class="w-full text-xs">
          <tbody>
            @foreach([
              ['Period',      $period->period],
              ['Fiscal Year', $period->fiscal_year],
              ['Month',       $period->fiscal_month],
              ['Status',      ucfirst($period->status)],
              ['Checklist',   $period->checklistProgress() . '%'],
              ['Closed At',   $period->closed_at?->format('d M Y H:i') ?? '—'],
              ['Locked At',   $period->locked_at?->format('d M Y H:i') ?? '—'],
              ['Notes',       Str::limit($period->notes ?? '—', 80)],
            ] as [$k,$v])
            <tr class="border-b border-kc-silver-light/60">
              <td class="py-1.5 text-kc-charcoal/70 pr-2">{{ $k }}</td>
              <td class="py-1.5 font-medium">{{ $v }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-app-layout>
