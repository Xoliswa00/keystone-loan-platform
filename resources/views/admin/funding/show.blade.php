<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">{{ $fundingFacility->facility_code }} — {{ $fundingFacility->facility_name }}</span>
    <p class="kc-page-subtitle">{{ \App\Models\FundingFacility::FUNDER_TYPES[$fundingFacility->funder_type] ?? '' }} · {{ ucfirst(str_replace('_',' ',$fundingFacility->status)) }}</p>
  </x-slot>

  @php $sc = \App\Models\FundingFacility::STATUS_CLASSES[$fundingFacility->status] ?? 'kc-badge-silver'; @endphp

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- LEFT: Main content --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- KPIs --}}
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Facility Limit</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($fundingFacility->facility_limit,2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Outstanding</p>
          <p class="font-display text-xl font-bold text-red-600 mt-1">R {{ number_format($fundingFacility->current_balance,2) }}</p>
          <p class="text-[10px] text-kc-charcoal/70">Balance sheet liability</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Available</p>
          <p class="font-display text-xl font-bold text-emerald-600 mt-1">R {{ number_format($fundingFacility->availableLimit(),2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Accrued Interest</p>
          <p class="font-display text-xl font-bold {{ $fundingFacility->accrued_interest > 0 ? 'text-orange-600' : 'text-kc-navy' }} mt-1">R {{ number_format($fundingFacility->accrued_interest,2) }}</p>
        </div>
      </div>

      {{-- Utilisation bar --}}
      <div class="kc-card">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-semibold text-kc-navy">Utilisation</span>
          <span class="text-sm font-bold text-kc-navy">{{ $fundingFacility->utilisationRate() }}%</span>
        </div>
        <div class="h-2.5 w-full bg-kc-silver-light rounded-full overflow-hidden">
          <div class="h-full {{ $fundingFacility->utilisationRate()>90?'bg-red-500':'bg-kc-gold' }} rounded-full transition-all"
               style="width:{{ min(100,$fundingFacility->utilisationRate()) }}%"></div>
        </div>
        <div class="flex justify-between text-xs text-kc-charcoal/70 mt-1">
          <span>R0</span>
          <span>R {{ number_format($fundingFacility->facility_limit,0) }}</span>
        </div>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-center text-xs">
          <div><p class="text-kc-charcoal/70">Monthly Interest Due</p><p class="font-semibold text-kc-navy">R {{ number_format($monthlyInterest,2) }}</p></div>
          <div><p class="text-kc-charcoal/70">Rate p.a.</p><p class="font-semibold">{{ round($fundingFacility->interest_rate*100,2) }}%</p></div>
          <div><p class="text-kc-charcoal/70">Payment Day</p><p class="font-semibold">{{ $fundingFacility->payment_day ? $fundingFacility->payment_day.'th' : '—' }}</p></div>
        </div>
      </div>

      {{-- Transaction history --}}
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Transaction History</h4>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Reference</th><th>GL Ref</th><th>Posted</th></tr></thead>
            <tbody>
              @forelse($fundingFacility->transactions as $txn)
              @php $tc = \App\Models\FundingFacilityTransaction::TYPE_CLASSES[$txn->transaction_type] ?? 'kc-badge-silver'; @endphp
              <tr>
                <td data-label="Date">{{ \Carbon\Carbon::parse($txn->transaction_date)->format('d M Y') }}</td>
                <td data-label="Type"><span class="kc-badge {{ $tc }}">{{ \App\Models\FundingFacilityTransaction::TYPE_LABELS[$txn->transaction_type] ?? $txn->transaction_type }}</span></td>
                <td data-label="Amount" class="font-semibold {{ in_array($txn->transaction_type,['drawdown']) ? 'text-emerald-600' : 'text-red-600' }}">
                  {{ in_array($txn->transaction_type,['drawdown']) ? '+' : '-' }} R {{ number_format($txn->amount,2) }}
                </td>
                <td data-label="Ref" class="text-xs">{{ $txn->reference ?? '—' }}</td>
                <td data-label="GL Ref" class="font-mono text-xs text-kc-charcoal/70">{{ $txn->gl_batch_reference ?? '—' }}</td>
                <td data-label="Posted">
                  <span class="kc-badge {{ $txn->gl_posted ? 'kc-badge-green' : 'kc-badge-silver' }} text-[10px]">
                    {{ $txn->gl_posted ? 'Posted' : 'Pending' }}
                  </span>
                </td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center py-6 text-kc-charcoal/70">No transactions yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- RIGHT: Actions + details --}}
    <div class="space-y-5">

      {{-- Record transaction --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Record Transaction</h5>
        <form method="POST" action="{{ route('admin.funding.transaction', $fundingFacility) }}" class="space-y-3" x-data="{type:'drawdown'}">
          @csrf
          <div>
            <label class="kc-label">Transaction Type</label>
            <select name="transaction_type" x-model="type" class="kc-select text-sm">
              <option value="drawdown">Drawdown (received funds)</option>
              <option value="repayment">Principal Repayment</option>
              <option value="interest_payment">Interest Payment</option>
            </select>
          </div>
          <div>
            <label class="kc-label">Amount (R)</label>
            <input type="number" name="amount" step="0.01" min="0.01" class="kc-input text-sm" required>
          </div>
          <div>
            <label class="kc-label">Date</label>
            <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" class="kc-input text-sm" required>
          </div>
          <div>
            <label class="kc-label">Bank Reference</label>
            <input type="text" name="reference" class="kc-input text-sm" placeholder="EFT ref / payment ref">
          </div>
          <div>
            <label class="kc-label">Notes</label>
            <textarea name="notes" rows="2" class="kc-input text-sm"></textarea>
          </div>
          <button type="submit" class="kc-btn-primary w-full justify-center text-sm">Record + Post to GL</button>
        </form>
      </div>

      {{-- Accrue interest --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-2">Monthly Interest Accrual</h5>
        <p class="text-xs text-kc-charcoal/70 mb-3">
          This month's accrual: <strong>R {{ number_format($monthlyInterest,2) }}</strong><br>
          Posts Dr Interest Expense / Cr Accrued Interest to GL.
        </p>
        <form method="POST" action="{{ route('admin.funding.accrue-all') }}">
          @csrf
          <button type="submit" class="kc-btn-ghost w-full justify-center text-xs">Run Accrual (All Facilities)</button>
        </form>
      </div>

      {{-- Facility details --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Facility Details</h5>
        <table class="w-full text-xs">
          <tbody>
            @foreach([
              ['Funder',$fundingFacility->funder_name],
              ['Type',\App\Models\FundingFacility::FUNDER_TYPES[$fundingFacility->funder_type]??$fundingFacility->funder_type],
              ['Contact',$fundingFacility->contact_person ?? '—'],
              ['Email',$fundingFacility->contact_email ?? '—'],
              ['Phone',$fundingFacility->contact_phone ?? '—'],
              ['Bank',$fundingFacility->bank_name ?? '—'],
              ['Account',$fundingFacility->bank_account_number ?? '—'],
              ['Start',$fundingFacility->facility_start_date?->format('d M Y') ?? '—'],
              ['Maturity',$fundingFacility->maturity_date?->format('d M Y') ?? 'Revolving'],
              ['Frequency',ucfirst($fundingFacility->payment_frequency)],
              ['Collateral',$fundingFacility->collateral_description ? Str::limit($fundingFacility->collateral_description,50) : '—'],
            ] as [$k,$v])
            <tr class="border-b border-kc-silver-light/60">
              <td class="py-1.5 text-kc-charcoal/70 pr-2">{{ $k }}</td>
              <td class="py-1.5 font-medium">{{ $v }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>

        @if($fundingFacility->agreement_document_path)
        <a href="{{ route('secure-documents.funding-agreement', $fundingFacility) }}"
           target="_blank" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted mt-3 block">
          View Facility Agreement (PDF)
        </a>
        @endif

        <a href="{{ route('admin.funding.edit', $fundingFacility) }}" class="kc-btn-ghost w-full justify-center text-xs mt-3">Edit Details</a>
      </div>
    </div>
  </div>
</x-app-layout>
