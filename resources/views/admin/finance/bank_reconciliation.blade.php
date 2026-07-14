<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Bank Reconciliation</span>
    <p class="kc-page-subtitle">{{ $meta['bank_name'] ?? '' }} · {{ $meta['account_name'] ?? '' }} · {{ $meta['period'] ?? '' }}</p>
  </x-slot>

  {{-- Reconciliation status banner --}}
  <div class="kc-card mb-5 {{ $status['complete'] ? 'border-l-4 border-emerald-400' : 'border-l-4 border-kc-gold' }}">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="font-display font-semibold text-kc-navy">
            {{ $status['complete'] ? '✓ Reconciliation Complete' : 'Reconciliation In Progress' }}
          </span>
          <span class="kc-badge {{ $status['completion_pct'] >= 100 ? 'kc-badge-green' : 'kc-badge-gold' }}">
            {{ $status['completion_pct'] }}% matched
          </span>
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-kc-charcoal/60">
          <span>{{ $status['total_lines'] }} total lines</span>
          <span class="text-emerald-600">{{ $status['matched_lines'] }} matched</span>
          <span class="{{ $status['unmatched_lines'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ $status['unmatched_lines'] }} unmatched</span>
          <span>Unallocated: R {{ number_format($status['unmatched_amount'] ?? 0, 2) }}</span>
        </div>
      </div>

      {{-- Balance reconciliation --}}
      <div class="text-right">
        @if($closingBalance !== null)
        <p class="text-xs text-kc-charcoal/50 mb-0.5">GL Bank Balance vs Statement</p>
        <p class="font-display text-lg font-bold {{ $status['balance_reconciled'] ? 'text-emerald-600' : 'text-red-600' }}">
          R {{ number_format($status['gl_bank_balance'], 2) }}
          @if($status['balance_reconciled'])
            = R {{ number_format($status['statement_closing'], 2) }} ✓
          @else
            ≠ R {{ number_format($status['statement_closing'], 2) }}
            <span class="text-xs">(var R {{ number_format(abs($status['statement_closing'] - $status['gl_bank_balance']), 2) }})</span>
          @endif
        </p>
        @endif
      </div>
    </div>

    <div class="w-full h-1.5 bg-kc-silver-light rounded-full overflow-hidden mt-3">
      <div class="h-full bg-emerald-500 rounded-full transition-all"
           style="width:{{ $status['completion_pct'] }}%"></div>
    </div>
  </div>

  {{-- Action bar --}}
  <div class="flex flex-wrap gap-3 mb-5">
    <form method="POST" action="{{ route('admin.finance.recon.auto-match', $batch->id) }}">
      @csrf
      <button type="submit" class="kc-btn-primary">Auto-Match All</button>
    </form>

    {{-- Set balances --}}
    <div x-data="{open:false}" class="relative">
      <button @click="open=!open" class="kc-btn-ghost">Set Statement Balances</button>
      <div x-show="open" @click.outside="open=false" x-transition
        class="absolute left-0 mt-2 w-72 bg-white rounded-xl shadow-kc-card border border-kc-silver-light p-4 z-50">
        <form method="POST" action="{{ route('admin.finance.recon.balances', $batch->id) }}" class="space-y-3">
          @csrf
          <div>
            <label class="kc-label">Opening Balance (R)</label>
            <input type="number" name="opening_balance" step="0.01" value="{{ $openingBalance }}" class="kc-input text-sm" required>
          </div>
          <div>
            <label class="kc-label">Closing Balance (R)</label>
            <input type="number" name="closing_balance" step="0.01" value="{{ $closingBalance }}" class="kc-input text-sm" required>
          </div>
          <button type="submit" class="kc-btn-primary w-full justify-center text-sm">Save</button>
        </form>
      </div>
    </div>

    @if($status['all_lines_matched'])
    <form method="POST" action="{{ route('admin.finance.recon.complete', $batch->id) }}">
      @csrf
      <button type="submit" class="kc-btn-primary bg-emerald-600 hover:bg-emerald-700"
        onclick="return confirm('Mark this reconciliation as complete and lock it?')">
        Mark Complete
      </button>
    </form>
    @endif
  </div>

  {{-- Category breakdown --}}
  @if($status['by_category']->isNotEmpty())
  <div class="kc-card mb-5">
    <h4 class="font-display font-semibold text-kc-navy mb-3">Matched by Category</h4>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      @foreach($status['by_category'] as $cat => $data)
      <div class="text-center p-3 rounded-lg bg-kc-silver-light/50">
        <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">{{ ucfirst(str_replace('_',' ',$cat)) }}</p>
        <p class="font-semibold text-kc-navy">{{ $data->count }}</p>
        <p class="text-xs text-kc-charcoal/50">R {{ number_format($data->total, 2) }}</p>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- ── UNMATCHED LINES — need allocation ── --}}
  @if($unmatched->isNotEmpty())
  <div class="kc-card mb-5" x-data="{ selected: [], selectAll: false }">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy text-red-600">
        Unallocated Lines ({{ $unmatched->count() }})
      </h4>
      {{-- Bulk allocation form --}}
      <div x-show="selected.length > 0" x-data="{open:false}" class="relative">
        <button @click="open=!open" class="kc-btn-primary text-sm">
          Bulk Allocate (<span x-text="selected.length"></span>)
        </button>
        <div x-show="open" @click.outside="open=false" x-transition
          class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-kc-card border border-kc-silver-light p-4 z-50">
          <form method="POST" action="{{ route('admin.finance.recon.bulk-allocate', $batch->id) }}" class="space-y-3">
            @csrf
            <template x-for="id in selected" :key="id">
              <input type="hidden" name="line_ids[]" :value="id">
            </template>
            <div>
              <label class="kc-label">Category</label>
              <select name="match_category" class="kc-select text-sm" required>
                <option value="expense">Expense</option>
                <option value="nu_pay_collection">Nu-Pay Collection</option>
                <option value="loan_disbursement">Loan Disbursement</option>
                <option value="facility_drawdown">Facility Drawdown</option>
                <option value="facility_repayment">Facility Repayment</option>
                <option value="inter_account">Inter-Account Transfer</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="kc-label">GL Account</label>
              <select name="gl_account_id" class="kc-select text-sm">
                <optgroup label="Quick Pick">
                  @foreach($quickPicks as $qp)
                    @php $glId = DB::table('gl_accounts as ga')->join('chart_of_accounts as coa','ga.chart_of_account_id','=','coa.id')->where('coa.account_code',$qp['code'])->value('ga.id'); @endphp
                    @if($glId)
                    <option value="{{ $glId }}">{{ $qp['label'] }} ({{ $qp['code'] }})</option>
                    @endif
                  @endforeach
                </optgroup>
                <optgroup label="All Expense Accounts">
                  @foreach($expenseAccounts as $acc)
                  <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ ucfirst($acc->account_type) }}</option>
                  @endforeach
                </optgroup>
              </select>
            </div>
            <div>
              <label class="kc-label">Description</label>
              <input type="text" name="description" class="kc-input text-sm" placeholder="e.g. Monthly bank charges" required>
            </div>
            <button type="submit" class="kc-btn-primary w-full justify-center text-sm">Post to GL</button>
          </form>
        </div>
      </div>
    </div>

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr>
            <th class="w-8">
              <input type="checkbox" x-model="selectAll"
                @change="selected = selectAll ? @json($unmatched->pluck('id')) : []"
                class="w-3.5 h-3.5 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30 cursor-pointer">
            </th>
            <th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Allocate</th>
          </tr>
        </thead>
        <tbody>
          @foreach($unmatched as $line)
          <tr class="{{ $line->match_status === 'exception' ? 'bg-red-50' : 'bg-amber-50/50' }}">
            <td>
              <input type="checkbox" :value="{{ $line->id }}" x-model="selected"
                class="w-3.5 h-3.5 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30 cursor-pointer">
            </td>
            <td data-label="Date" class="text-xs">{{ $line->transaction_date }}</td>
            <td data-label="Description" class="text-xs max-w-xs">{{ Str::limit($line->description, 50) }}</td>
            <td data-label="Debit" class="text-red-600 font-semibold">{{ $line->debit_amount > 0 ? 'R '.number_format($line->debit_amount,2) : '' }}</td>
            <td data-label="Credit" class="text-emerald-600 font-semibold">{{ $line->credit_amount > 0 ? 'R '.number_format($line->credit_amount,2) : '' }}</td>
            <td data-label="Balance" class="text-xs text-kc-charcoal/50">{{ $line->running_balance ? 'R '.number_format($line->running_balance,2) : '—' }}</td>
            <td data-label="Allocate">
              <div x-data="{open:false}" class="relative">
                <button @click="open=!open" class="kc-btn-ghost text-xs py-1 px-3">Allocate</button>
                <div x-show="open" @click.outside="open=false" x-transition
                  class="absolute right-0 mt-1 w-72 bg-white rounded-xl shadow-kc-card border border-kc-silver-light p-3 z-50">
                  <form method="POST" action="{{ route('admin.finance.recon.allocate', $batch->id) }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="line_id" value="{{ $line->id }}">
                    <div>
                      <label class="kc-label">Category</label>
                      <select name="match_category" class="kc-select text-xs" required>
                        @foreach([
                          'expense'=>'Expense',
                          'nu_pay_collection'=>'Nu-Pay Collection',
                          'loan_disbursement'=>'Loan Disbursement',
                          'facility_drawdown'=>'Facility Drawdown',
                          'facility_repayment'=>'Facility Repayment',
                          'inter_account'=>'Inter-Account',
                          'other'=>'Other',
                        ] as $v=>$l)
                        <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label class="kc-label">GL Account</label>
                      <select name="gl_account_id" class="kc-select text-xs">
                        <optgroup label="Quick">
                          @foreach($quickPicks as $qp)
                            @php $glId = DB::table('gl_accounts as ga')->join('chart_of_accounts as coa','ga.chart_of_account_id','=','coa.id')->where('coa.account_code',$qp['code'])->value('ga.id'); @endphp
                            @if($glId)<option value="{{ $glId }}">{{ $qp['label'] }}</option>@endif
                          @endforeach
                        </optgroup>
                        <optgroup label="All">
                          @foreach($expenseAccounts as $acc)
                          <option value="{{ $acc->id }}">{{ $acc->account_code }}</option>
                          @endforeach
                        </optgroup>
                      </select>
                    </div>
                    <div>
                      <input type="text" name="description" class="kc-input text-xs"
                        placeholder="{{ Str::limit($line->description,30) }}" required>
                    </div>
                    <button type="submit" class="kc-btn-primary w-full justify-center text-xs">Post to GL</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @else
  <div class="kc-alert-success mb-5 flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    All lines are matched. Check the balance reconciliation above to complete.
  </div>
  @endif

  {{-- ── MATCHED LINES — audit trail ── --}}
  <div class="kc-card">
    <div class="flex items-center justify-between mb-3">
      <h4 class="font-display font-semibold text-kc-navy">Matched Lines ({{ $matched->count() }})</h4>
    </div>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Category</th><th>GL Ref</th><th>Action</th>
        </tr></thead>
        <tbody>
          @foreach($matched as $line)
          @php $catClass = match($line->match_category ?? '') {
            'nu_pay_collection'  => 'kc-badge-green',
            'loan_disbursement'  => 'kc-badge-navy',
            'facility_drawdown'  => 'kc-badge-navy',
            'facility_repayment' => 'kc-badge-gold',
            'expense'            => 'kc-badge-red',
            default              => 'kc-badge-silver',
          }; @endphp
          <tr>
            <td data-label="Date" class="text-xs">{{ $line->transaction_date }}</td>
            <td data-label="Description" class="text-xs max-w-xs truncate">{{ $line->description }}</td>
            <td data-label="Debit" class="text-red-600">{{ $line->debit_amount > 0 ? 'R '.number_format($line->debit_amount,2) : '' }}</td>
            <td data-label="Credit" class="text-emerald-600">{{ $line->credit_amount > 0 ? 'R '.number_format($line->credit_amount,2) : '' }}</td>
            <td data-label="Category"><span class="kc-badge {{ $catClass }} text-[10px]">{{ ucfirst(str_replace('_',' ',$line->match_category ?? 'matched')) }}</span></td>
            <td data-label="GL Ref" class="font-mono text-[10px] text-kc-charcoal/40">{{ $line->allocation_gl_batch_ref ?? $line->match_note ? Str::limit($line->match_note,30) : '—' }}</td>
            <td data-label="Action">
              @if(!$status['all_lines_matched'] || $batch->status !== 'RECONCILED')
              <form method="POST" action="{{ route('admin.finance.recon.unallocate', $batch->id) }}"
                    onsubmit="return confirm('Undo this allocation? This will reverse any posted GL entry and return the line to unmatched.')">
                @csrf
                <input type="hidden" name="line_id" value="{{ $line->id }}">
                <button type="submit" class="kc-btn-ghost text-[10px] py-1 px-2">Undo</button>
              </form>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
