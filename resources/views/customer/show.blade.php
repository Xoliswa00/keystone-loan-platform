<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">{{ $user->name }}</span>
    <p class="kc-page-subtitle">{{ $customer->customer_code }} · {{ $user->ID_Number }}</p>
  </x-slot>

  {{-- Status bar --}}
  <div class="flex flex-wrap items-center gap-2 mb-5">
    @if($user->blacklisted)
      <span class="kc-badge kc-badge-red">Blacklisted</span>
    @elseif($user->applications_restricted)
      <span class="kc-badge kc-badge-gold">Restricted</span>
    @else
      <span class="kc-badge kc-badge-green">Active</span>
    @endif
    @if(!$limitCheck['allowed'])
      <span class="kc-badge kc-badge-gold text-[10px]">Cannot Apply: {{ str_replace('gate:','',ucfirst(str_replace('_',' ',$limitCheck['gate']??''))) }}</span>
    @endif
    @if($recoveryCase)
      <span class="kc-badge kc-badge-red text-[10px]">Recovery Case Open</span>
    @endif
    @if($user->extended_terms_eligible)
      <span class="kc-badge kc-badge-navy text-[10px]">Extended Terms Eligible</span>
    @endif
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- MAIN: Profile + Loans + History ──────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- Summary cards --}}
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase">Outstanding</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($customer->current_balance??0,2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase">Active Loans</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">{{ $loans->whereNotIn('status',['settled','rejected','archived','written_off'])->count() }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase">Applications</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">{{ $user->loanApplications->count() }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/70 uppercase">Profile</p>
          <p class="font-display text-xl font-bold {{ $profileStatus['percentage']===100?'text-emerald-600':'text-kc-navy' }} mt-1">{{ $profileStatus['percentage'] }}%</p>
        </div>
      </div>

      {{-- Personal details --}}
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Personal Details</h5>
        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          @foreach([
            ['Name',      $user->name],
            ['SA ID',     $user->ID_Number],
            ['Email',     $user->email],
            ['Phone',     $user->phone],
            ['Address',   $user->address],
            ['Employer',  $user->customerProfile?->employer_name ?? '—'],
            ['Employment',ucfirst(str_replace('_',' ',$user->customerProfile?->employment_type??'—'))],
            ['Tenure',    ucfirst(str_replace('_',' ',$user->customerProfile?->employment_tenure??'—'))],
            ['Net Salary','R '.number_format($user->customerProfile?->net_monthly_income??0,2)],
            ['Payday',    $user->salary_payment_day ? $user->salary_payment_day.'th' : '—'],
          ] as [$k,$v])
          <div class="flex gap-2">
            <span class="text-kc-charcoal/70 text-xs flex-shrink-0 w-24">{{ $k }}</span>
            <span class="font-medium text-xs truncate">{{ $v }}</span>
          </div>
          @endforeach
        </div>

        {{-- Affordability --}}
        @if($affordability['eligible'] ?? false)
        <div class="mt-3 pt-3 border-t border-kc-silver-light grid grid-cols-3 gap-3 text-center text-xs">
          <div><p class="text-kc-charcoal/70">Total Income</p><p class="font-semibold">R {{ number_format($affordability['total_income']??0,2) }}</p></div>
          <div><p class="text-kc-charcoal/70">Total Expenses</p><p class="font-semibold">R {{ number_format($affordability['total_expenses']??0,2) }}</p></div>
          <div><p class="text-kc-charcoal/70">Disposable</p><p class="font-semibold {{ ($affordability['disposable_income']??0) > 0 ? 'text-emerald-600':'text-red-600' }}">R {{ number_format($affordability['disposable_income']??0,2) }}</p></div>
        </div>
        @endif

        {{-- Bank analysis --}}
        @if($user->customerProfile?->bank_analysis_run_at)
        @php $rf=$user->customerProfile->bank_statement_risk_flag??'low'; $rfCls=['low'=>'kc-badge-green','medium'=>'kc-badge-gold','high'=>'kc-badge-red','very_high'=>'kc-badge-red'][$rf]??'kc-badge-silver'; @endphp
        <div class="mt-3 pt-3 border-t border-kc-silver-light flex flex-wrap gap-3 text-xs items-center">
          <span class="kc-badge {{ $rfCls }}">Bank Risk: {{ strtoupper(str_replace('_',' ',$rf)) }}</span>
          <span class="text-kc-charcoal/70">Days to R500 after payday: <strong>{{ $user->customerProfile->avg_days_to_zero ?? '—' }}</strong></span>
          <span class="text-kc-charcoal/70">Verified salary: <strong>R {{ number_format($user->customerProfile->verified_income_amount??0,0) }}</strong></span>
        </div>
        @endif
      </div>

      {{-- Loans --}}
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Loan History</h5>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Loan</th><th>Amount</th><th>Status</th><th>Remaining</th><th>Disbursed</th></tr></thead>
            <tbody>
              @forelse($loans as $loan)
              @php $lsc=match(strtolower($loan->status??'')){'disbursed','approved'=>'kc-badge-green','settled'=>'kc-badge-silver','written_off','rejected'=>'kc-badge-red',default=>'kc-badge-gold'}; @endphp
              <tr>
                <td data-label="Loan" class="font-mono text-xs">#{{ str_pad($loan->id,6,'0',STR_PAD_LEFT) }}</td>
                <td data-label="Amount">R {{ number_format($loan->loan_amount,2) }}</td>
                <td data-label="Status"><span class="kc-badge {{ $lsc }}">{{ ucfirst($loan->status) }}</span></td>
                <td data-label="Remaining" class="{{ $loan->remaining_balance>0?'font-semibold':'' }}">R {{ number_format($loan->remaining_balance??0,2) }}</td>
                <td data-label="Disbursed" class="text-xs text-kc-charcoal/70">{{ $loan->disbursed_date ? \Carbon\Carbon::parse($loan->disbursed_date)->format('d M Y') : '—' }}</td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-center py-4 text-kc-charcoal/70">No loans.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Recent payments --}}
      @if($repayments->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Recent Payments</h5>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>GL Ref</th></tr></thead>
            <tbody>
              @foreach($repayments as $r)
              <tr>
                <td data-label="Date">{{ \Carbon\Carbon::parse($r->payment_date)->format('d M Y') }}</td>
                <td data-label="Amount" class="font-semibold text-emerald-600">R {{ number_format($r->payment_amount,2) }}</td>
                <td data-label="Method" class="text-xs">{{ ucfirst(str_replace('_',' ',$r->payment_method??'—')) }}</td>
                <td data-label="GL" class="font-mono text-[10px] text-kc-charcoal/70">{{ $r->gl_batch_reference ?? '—' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Notes --}}
      @if($notes->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Admin Notes</h5>
        <div class="space-y-2">
          @foreach($notes as $note)
          <div class="flex gap-2 text-sm">
            <div class="w-5 h-5 rounded-full bg-kc-navy flex-shrink-0 flex items-center justify-center mt-0.5">
              <span class="text-kc-gold text-[8px] font-bold">{{ strtoupper(substr($note->admin_name,0,1)) }}</span>
            </div>
            <div>
              <p class="text-xs text-kc-charcoal/70">{{ $note->admin_name }} · {{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }} · <span class="kc-badge kc-badge-silver text-[9px]">{{ $note->note_type }}</span></p>
              <p>{{ $note->note }}</p>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    {{-- RIGHT: Actions --}}
    <div class="space-y-5">

      {{-- Documents --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">KYC Documents</h5>
        @php $docs = $user->customerDocuments->keyBy('document_type'); @endphp
        @foreach(\App\Models\CustomerDocument::TYPES as $type => $label)
        @php $doc = $docs[$type] ?? null; @endphp
        <div class="flex items-center justify-between py-1.5 border-b border-kc-silver-light/60 last:border-0">
          <div class="flex items-center gap-1.5">
            @if($doc?->verified)
              <div class="w-4 h-4 rounded-full bg-emerald-500 flex-shrink-0 flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              </div>
            @elseif($doc)
              <div class="w-4 h-4 rounded-full bg-kc-gold/20 border border-kc-gold/40 flex-shrink-0"></div>
            @else
              <div class="w-4 h-4 rounded-full border border-kc-silver-light flex-shrink-0"></div>
            @endif
            <span class="text-xs text-kc-charcoal/70">{{ $label }}</span>
          </div>
          @if($doc)
          <div class="flex items-center gap-1.5">
            <a href="{{ route('secure-documents.customer-document', $doc) }}" target="_blank" class="text-[10px] text-kc-navy underline underline-offset-2 hover:underline">View</a>
            @if(!$doc->verified && Auth::user()->hasRole('loan_officer', 'it_admin'))
            <form method="POST" action="{{ route('admin.documents.verify', $doc) }}" class="inline">
              @csrf <input type="hidden" name="verified" value="1">
              <button type="submit" class="text-[10px] text-emerald-600 hover:underline">Verify</button>
            </form>
            @endif
          </div>
          @else
          <span class="text-[10px] text-kc-charcoal/70">Missing</span>
          @endif
        </div>
        @endforeach
      </div>

      {{-- Limitation status --}}
      @if(!$limitCheck['allowed'])
      <div class="kc-card border-l-4 border-amber-400">
        <h5 class="font-semibold text-kc-navy mb-2">Application Block</h5>
        <p class="text-xs text-kc-charcoal/70">{{ $limitCheck['reason'] }}</p>
        @if($limitCheck['unblock_at'])
        <p class="text-xs text-amber-700 font-semibold mt-1">Unblocks: {{ \Carbon\Carbon::parse($limitCheck['unblock_at'])->format('d M Y') }}</p>
        @endif
      </div>
      @endif

      {{-- Account controls --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Account Controls</h5>
        <div class="space-y-2">

          {{-- Restrict / Lift --}}
          @if(!$user->applications_restricted)
          <div x-data="{open:false}">
            <button @click="open=!open" class="kc-btn-ghost w-full justify-center text-xs text-orange-600 border-orange-200">Restrict Applications</button>
            <div x-show="open" x-transition class="mt-2 p-3 rounded-lg border border-kc-silver-light bg-kc-silver-light/30">
              <form method="POST" action="{{ route('customers.restrict', $customer) }}" class="space-y-2">
                @csrf
                <textarea name="reason" rows="2" class="kc-input text-xs" placeholder="Reason for restriction" required></textarea>
                <input type="date" name="expires_at" class="kc-input text-xs" placeholder="Expiry (optional)">
                <button type="submit" class="kc-btn-danger w-full justify-center text-xs">Apply Restriction</button>
              </form>
            </div>
          </div>
          @else
          <form method="POST" action="{{ route('customers.lift', $customer) }}">
            @csrf
            <button type="submit" class="kc-btn-primary w-full justify-center text-xs">Lift Restriction</button>
          </form>
          @endif

          {{-- Blacklist toggle --}}
          <form method="POST" action="{{ route('customers.blacklist', $customer) }}">
            @csrf
            <button type="submit" class="kc-btn-ghost w-full justify-center text-xs {{ $user->blacklisted?'text-emerald-600 border-emerald-200':'text-red-600 border-red-200' }}"
              onclick="return confirm('{{ $user->blacklisted ? 'Remove from blacklist?' : 'Blacklist this client?' }}')">
              {{ $user->blacklisted ? 'Remove Blacklist' : 'Add to Blacklist' }}
            </button>
          </form>

          {{-- Extended-term override — lets this one client apply for
               multi-month products even while they stay inactive for
               everyone else. Independent of the global toggle on the Loan
               Products admin page. --}}
          <form method="POST" action="{{ route('customers.extended-terms', $customer) }}">
            @csrf
            <button type="submit" class="kc-btn-ghost w-full justify-center text-xs {{ $user->extended_terms_eligible ? 'text-emerald-600 border-emerald-200' : '' }}"
              onclick="return confirm('{{ $user->extended_terms_eligible ? 'Revoke extended-term eligibility?' : 'Allow this client to apply for extended-term (multi-month) loans?' }}')">
              {{ $user->extended_terms_eligible ? 'Revoke Extended Terms' : 'Grant Extended Terms' }}
            </button>
          </form>

          {{-- Statement download --}}
          <a href="{{ route('client.statement', $user) }}" class="kc-btn-ghost w-full justify-center text-xs">Download Statement</a>

          {{-- View agreements --}}
          @php $anyApp = $user->loanApplications->first(); @endphp
          @if($anyApp)
          <a href="{{ route('agreements.index', $anyApp) }}" class="kc-btn-ghost w-full justify-center text-xs">View Agreements</a>
          @endif

          {{-- Recovery case --}}
          @if($recoveryCase)
          <a href="{{ route('admin.recovery.show', $recoveryCase) }}" class="kc-btn-ghost w-full justify-center text-xs text-kc-gold border-kc-gold/30">View Recovery Case</a>
          @endif
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
