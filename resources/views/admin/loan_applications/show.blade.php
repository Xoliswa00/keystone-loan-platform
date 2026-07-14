<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Review Application #{{ str_pad($application->id, 6, '0', STR_PAD_LEFT) }}</span>
    <p class="kc-page-subtitle">{{ $application->user?->name }} · Submitted {{ $application->created_at->format('d M Y H:i') }}</p>
  </x-slot>

  @php
    $statusMap = ['pending'=>'kc-badge-gold','approved'=>'kc-badge-green','rejected'=>'kc-badge-red','under_review'=>'kc-badge-navy'];
    $sc = $statusMap[strtolower($application->status)] ?? 'kc-badge-silver';
  @endphp
  <div class="flex flex-wrap items-center gap-2 mb-6">
    <span class="kc-badge {{ $sc }} text-sm px-4 py-1.5">{{ ucfirst($application->status) }}</span>
    @if($application->product)<span class="kc-badge kc-badge-navy">{{ $application->product->name }}</span>@endif
  </div>

  <div class="flex justify-end mb-3">
    <form method="POST" action="{{ route('admin.applications.reassess', $application) }}">
      @csrf
      <button type="submit" class="kc-btn-ghost text-xs">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Re-run CLO Assessment
      </button>
    </form>
  </div>

  @if($cloDecision)
    @php
      $cloBadgeMap = ['APPROVE'=>'kc-badge-green','REJECT'=>'kc-badge-red','ESCALATE'=>'kc-badge-red','REVIEW'=>'kc-badge-gold'];
      $cloBadge = $cloBadgeMap[$cloDecision->decision] ?? 'kc-badge-silver';
    @endphp
    <div class="kc-card mb-6">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h5 class="font-semibold text-kc-navy">CLO Decision (advisory)</h5>
        <span class="kc-badge {{ $cloBadge }}">{{ $cloDecision->decision }}</span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Risk Score</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">{{ $cloDecision->risk_score }}/100</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Compliance</p>
          <p class="font-display text-xl font-bold {{ $cloDecision->compliance_status === 'PASS' ? 'text-emerald-600' : 'text-red-600' }} mt-1">
            {{ $cloDecision->compliance_status }}
          </p>
        </div>
      </div>
      <p class="text-sm text-kc-charcoal/70 mb-2">{{ $cloDecision->reason }}</p>
      @if(!empty($cloDecision->fraud_flags))
        <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider mb-1">Flags</p>
        <div class="flex flex-wrap gap-1.5 mb-2">
          @foreach($cloDecision->fraud_flags as $flag)
            <span class="kc-badge kc-badge-silver text-xs">{{ $flag }}</span>
          @endforeach
        </div>
      @endif
      @if(!empty($cloDecision->required_actions))
        <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider mb-1">Required Actions</p>
        <ul class="text-sm list-disc list-inside text-kc-charcoal/70">
          @foreach($cloDecision->required_actions as $action)
            <li>{{ $action }}</li>
          @endforeach
        </ul>
      @endif
      <p class="text-xs text-kc-charcoal/40 mt-3">Evaluated {{ $cloDecision->evaluated_at->format('d M Y H:i') }} · advisory only, does not block manual approval/rejection</p>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

      {{-- Summary cards --}}
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Requested</p>
          <p class="font-display text-xl font-bold text-kc-gold mt-1">R {{ number_format($application->loan_amount,2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Total Due</p>
          <p class="font-display text-xl font-bold text-kc-navy mt-1">R {{ number_format($application->loanfee?->total_due ?? 0, 2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Outstanding</p>
          <p class="font-display text-xl font-bold text-red-600 mt-1">R {{ number_format($application->user?->customer?->current_balance ?? 0, 2) }}</p>
        </div>
        <div class="kc-stat-card text-center">
          <p class="text-xs text-kc-charcoal/50 uppercase tracking-wider">Rejections</p>
          <p class="font-display text-xl font-bold {{ ($application->user?->loanApplications()->where('status','rejected')->count() ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-1">
            {{ $application->user?->loanApplications()->where('status','rejected')->count() ?? 0 }}
          </p>
        </div>
      </div>

      {{-- Applicant & loan --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="kc-card">
          <h5 class="font-semibold text-kc-navy mb-3">Applicant Details</h5>
          <table class="w-full text-sm">
            <tbody>
              @foreach([
                ['Name',        $application->user?->name],
                ['SA ID',       $application->user?->ID_Number],
                ['Customer',    $application->user?->customer?->customer_code ?? '—'],
                ['Phone',       $application->user?->phone],
                ['Email',       $application->user?->email],
                ['Employment',  $application->user?->customerProfile?->employment_type ? ucfirst(str_replace('_', ' ', $application->user->customerProfile->employment_type)) : '—'],
                ['Net Income',  'R ' . number_format($application->user?->customerProfile?->net_monthly_income ?? 0, 2)],
                ['Payday',      $application->user?->salary_payment_day ? $application->user->salary_payment_day . 'th' : '—'],
              ] as [$k,$v])
              <tr class="border-b border-kc-silver-light/60">
                <td class="py-2 text-xs text-kc-charcoal/50 pr-3">{{ $k }}</td>
                <td class="py-2 text-xs font-medium">{{ $v }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="kc-card">
          <h5 class="font-semibold text-kc-navy mb-3">Loan Terms</h5>
          <table class="w-full text-sm">
            <tbody>
              @foreach([
                ['Type',          ucfirst($application->loan_type)],
                ['Amount',        'R ' . number_format($application->loan_amount, 2)],
                ['Term',          ($application->loan_term_months ?? 1) . ' month(s)'],
                ['Interest',      ($application->loanfee ? number_format((float)$application->loanfee->interest_rate*100,2).'%' : '—') . ' p/m'],
                ['Init Fee',      'R ' . number_format($application->loanfee?->initiation_fee ?? 0, 2)],
                ['Service Fee',   'R ' . number_format($application->loanfee?->service_fee ?? 0, 2)],
                ['Total Interest','R ' . number_format($application->loanfee?->interest_amount ?? 0, 2)],
                ['TOTAL DUE',     'R ' . number_format($application->loanfee?->total_due ?? 0, 2)],
              ] as [$k,$v])
              <tr class="border-b border-kc-silver-light/60">
                <td class="py-2 text-xs text-kc-charcoal/50 pr-3">{{ $k }}</td>
                <td class="py-2 text-xs font-medium {{ $k === 'TOTAL DUE' ? 'font-bold text-kc-navy' : '' }}">{{ $v }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      {{-- Affordability --}}
      @if($application->affordability_checked)
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3 flex items-center gap-2">
          Affordability Assessment (NCA s.81)
          <span class="kc-badge {{ ($application->affordability_instalment_requested ?? 0) <= ($application->affordability_max_instalment ?? 1) ? 'kc-badge-green' : 'kc-badge-red' }}">
            {{ ($application->affordability_instalment_requested ?? 0) <= ($application->affordability_max_instalment ?? 1) ? 'PASSES' : 'FAILS' }}
          </span>
        </h5>
        <div class="grid grid-cols-3 gap-4 text-center">
          <div><p class="text-xs text-kc-charcoal/50">Disposable Income</p><p class="font-display text-xl font-bold text-kc-navy">R {{ number_format($application->affordability_disposable_income ?? 0, 2) }}</p></div>
          <div><p class="text-xs text-kc-charcoal/50">Max Instalment (30%)</p><p class="font-display text-xl font-bold text-kc-navy">R {{ number_format($application->affordability_max_instalment ?? 0, 2) }}</p></div>
          <div><p class="text-xs text-kc-charcoal/50">Requested</p>
            <p class="font-display text-xl font-bold {{ ($application->affordability_instalment_requested ?? 0) <= ($application->affordability_max_instalment ?? 1) ? 'text-emerald-600' : 'text-red-600' }}">
              R {{ number_format($application->affordability_instalment_requested ?? 0, 2) }}
            </p>
          </div>
        </div>

        {{-- Bank analysis --}}
        @php $profile = $application->user?->customerProfile; @endphp
        @if($profile?->bank_analysis_run_at)
        <div class="mt-4 pt-4 border-t border-kc-silver-light grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div><p class="font-display text-lg font-bold {{ ($profile->avg_days_to_zero ?? 99) < 7 ? 'text-red-600' : 'text-kc-navy' }}">{{ $profile->avg_days_to_zero ?? '—' }}</p><p class="text-[10px] text-kc-charcoal/50">Days to R500 after payday</p></div>
          <div><p class="font-display text-lg font-bold text-kc-navy">R {{ number_format($profile->verified_income_amount ?? 0, 0) }}</p><p class="text-[10px] text-kc-charcoal/50">Verified salary</p></div>
          <div><p class="font-display text-lg font-bold text-kc-navy">R {{ number_format($profile->detected_regular_debits ?? 0, 0) }}</p><p class="text-[10px] text-kc-charcoal/50">Detected debits</p></div>
          <div>
            @php $rf=$profile->bank_statement_risk_flag??'low'; $rfCls=['low'=>'kc-badge-green','medium'=>'kc-badge-gold','high'=>'kc-badge-red','very_high'=>'kc-badge-red'][$rf]??'kc-badge-silver'; @endphp
            <span class="kc-badge {{ $rfCls }}">{{ strtoupper(str_replace('_',' ',$rf)) }}</span>
            <p class="text-[10px] text-kc-charcoal/50 mt-1">Bank risk</p>
          </div>
        </div>
        @if($profile->bank_statement_risk_reason)
          <p class="text-xs text-kc-charcoal/50 mt-2">{{ $profile->bank_statement_risk_reason }}</p>
        @endif
        @endif
      </div>
      @endif

      {{-- Repayment schedule --}}
      @if($application->repaymentSchedules->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Repayment Schedule</h5>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Instalment</th></tr></thead>
            <tbody>
              @foreach($application->repaymentSchedules->sortBy('installment_number') as $s)
              <tr>
                <td data-label="#">{{ $s->installment_number }}</td>
                <td data-label="Due">{{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}</td>
                <td data-label="Principal">R {{ number_format($s->principal_amount,2) }}</td>
                <td data-label="Interest">R {{ number_format($s->interest_amount,2) }}</td>
                <td data-label="Fees">R {{ number_format($s->fee_amount,2) }}</td>
                <td data-label="Instalment" class="font-semibold">R {{ number_format($s->emi_amount,2) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Previous loans --}}
      @if($previousLoans->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Previous Applications ({{ $previousLoans->count() }})</h5>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Ref</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              @foreach($previousLoans as $prev)
              @php $psc = match(strtolower($prev->status??'')){'approved','disbursed','settled'=>'kc-badge-green','pending'=>'kc-badge-gold','rejected'=>'kc-badge-red',default=>'kc-badge-silver'}; @endphp
              <tr>
                <td data-label="Ref" class="font-mono text-xs">#{{ str_pad($prev->id,6,'0',STR_PAD_LEFT) }}</td>
                <td data-label="Amount">R {{ number_format($prev->loan_amount,2) }}</td>
                <td data-label="Status"><span class="kc-badge {{ $psc }}">{{ ucfirst($prev->status) }}</span></td>
                <td data-label="Date" class="text-xs text-kc-charcoal/50">{{ $prev->created_at->format('d M Y') }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Notes --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Notes</h5>
        @php
          $notes = \Illuminate\Support\Facades\DB::table('admin_notes')
            ->where('loan_application_id', $application->id)
            ->join('users','users.id','=','admin_notes.user_id')
            ->select('admin_notes.*','users.name as admin_name')
            ->orderByDesc('admin_notes.created_at')->get();
        @endphp
        @foreach($notes as $note)
        <div class="flex gap-2 mb-3">
          <div class="w-6 h-6 rounded-full bg-kc-navy flex-shrink-0 flex items-center justify-center">
            <span class="text-kc-gold text-[9px] font-bold">{{ strtoupper(substr($note->admin_name,0,1)) }}</span>
          </div>
          <div>
            <p class="text-xs text-kc-charcoal/50">{{ $note->admin_name }} · {{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</p>
            <p class="text-sm">{{ $note->note }}</p>
          </div>
        </div>
        @endforeach
        <form method="POST" action="{{ route('admin.notes.store', $application) }}" class="flex gap-2 mt-2">
          @csrf
          <input type="hidden" name="note_type" value="general">
          <input type="text" name="note" class="kc-input flex-1 text-sm" placeholder="Add a note..." required>
          <button type="submit" class="kc-btn-ghost text-sm">Add</button>
        </form>
      </div>
    </div>

    {{-- SIDEBAR --}}
    <div class="space-y-5">

      {{-- Decision --}}
      @if(in_array(strtolower($application->status), ['pending','under_review']))
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Decision</h5>

        @if($application->nca_agreement_sent_at)
          <a href="{{ route('agreements.index', $application) }}" class="kc-btn-ghost w-full justify-center text-xs mb-3">
            View Pre-Agreement (sent {{ \Carbon\Carbon::parse($application->nca_agreement_sent_at)->format('d M Y') }})
          </a>
        @else
          <form method="POST" action="{{ route('admin.agreements.pre-agreement', $application) }}" class="mb-3">
            @csrf
            <button type="submit" class="kc-btn-ghost w-full justify-center text-xs">Pre-Agreement (NCA s.92)</button>
          </form>
        @endif

        <form method="POST" action="{{ route('loans.approve', $application->id) }}" class="mb-3">
          @csrf
          <textarea name="approval_comments" rows="2" class="kc-input w-full mb-2 text-sm" placeholder="Approval notes (optional)"></textarea>
          <button type="submit" class="kc-btn-primary w-full justify-center">✓ Approve</button>
        </form>

        <div x-data="{open:false}">
          <button @click="open=!open" class="kc-btn-danger w-full justify-center text-sm">✗ Reject</button>
          <div x-show="open" x-transition class="mt-2 space-y-2">
            <form method="POST" action="{{ route('loans.reject', $application->id) }}">
              @csrf
              <textarea name="rejection_reason" rows="2" class="kc-input w-full text-sm" placeholder="Reason (required)" required></textarea>
              <button type="submit" class="kc-btn-danger w-full justify-center text-sm mt-2">Confirm Reject</button>
            </form>
          </div>
        </div>
      </div>
      @endif

      {{-- Assign --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Assign Reviewer</h5>
        <form method="POST" action="{{ route('admin.assign', $application) }}" class="space-y-2">
          @csrf
          <select name="reviewer_id" class="kc-select text-sm">
            <option value="">Select officer...</option>
            @foreach(\App\Models\User::where('rule_id',2)->orderBy('name')->get() as $u)
              <option value="{{ $u->id }}" {{ $application->reviewer_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="kc-btn-ghost w-full justify-center text-xs">Assign</button>
        </form>
      </div>

      {{-- Documents --}}
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Documents</h5>
        @foreach([['payslips','Payslip'],['bank_statement','Bank Statement'],['credit_score_report','Credit Report']] as [$field,$label])
        @php $fp = $application->$field; @endphp
        <div class="flex items-center justify-between py-1.5 border-b border-kc-silver-light/60 last:border-0">
          <span class="text-xs text-kc-charcoal/70">{{ $label }}</span>
          @if($fp)
            <a href="{{ asset('storage/'.$fp) }}" target="_blank" class="text-xs text-kc-gold hover:underline">View</a>
          @else
            <span class="text-[10px] text-kc-charcoal/30">Missing</span>
          @endif
        </div>
        @endforeach

        @php $kycDocs = $application->user?->customerDocuments()->get() ?? collect(); @endphp
        @if($kycDocs->isNotEmpty())
        <p class="text-xs font-semibold text-kc-charcoal/50 mt-3 mb-1 uppercase">KYC</p>
        @foreach($kycDocs as $kd)
        <div class="flex items-center justify-between py-1">
          <div class="flex items-center gap-1">
            @if($kd->verified)
              <svg class="w-3 h-3 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            @else
              <div class="w-3 h-3 rounded-full border border-kc-silver flex-shrink-0"></div>
            @endif
            <span class="text-xs text-kc-charcoal/70">{{ $kd->getTypeLabel() }}</span>
            @if($kd->content_check_status === 'plausible')
              <span title="{{ $kd->content_check_notes }}" class="kc-badge kc-badge-green text-[9px]">auto-check ✓</span>
            @elseif($kd->content_check_status === 'inconclusive')
              <span title="{{ $kd->content_check_notes }}" class="kc-badge kc-badge-gold text-[9px]">auto-check ⚠</span>
            @elseif($kd->content_check_status === 'pending')
              <span class="kc-badge kc-badge-silver text-[9px]">checking…</span>
            @endif
          </div>
          <div class="flex items-center gap-1.5">
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($kd->file_path) }}" target="_blank" class="text-[10px] text-kc-gold hover:underline">View</a>
            @if(!$kd->verified)
            <form method="POST" action="{{ route('admin.documents.verify', $kd) }}" class="inline">
              @csrf <input type="hidden" name="verified" value="1">
              <button type="submit" class="text-[10px] text-emerald-600 hover:underline">Verify</button>
            </form>
            @endif
          </div>
        </div>
        @endforeach
        @endif
      </div>

      {{-- Agreements --}}
      @php $agreements = \App\Models\NcaAgreement::where('loan_application_id',$application->id)->get(); @endphp
      @if($agreements->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">NCA Documents</h5>
        @foreach($agreements as $ag)
        <div class="flex items-center justify-between py-1.5 border-b border-kc-silver-light/60 last:border-0">
          <div>
            <p class="text-xs font-medium">{{ $ag->getTypeLabel() }}</p>
            <p class="text-[10px] text-kc-charcoal/40">{{ $ag->reference }}</p>
          </div>
          <div class="flex items-center gap-1.5">
            @if($ag->accepted_at)<span class="kc-badge kc-badge-green text-[9px]">Signed</span>@endif
            <a href="{{ route('agreements.download', $ag) }}" class="text-xs text-kc-gold">PDF</a>
          </div>
        </div>
        @endforeach
      </div>
      @endif
    </div>
  </div>
</x-app-layout>
