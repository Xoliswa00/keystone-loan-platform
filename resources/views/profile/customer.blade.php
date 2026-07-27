<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">My Financial Profile</span>
    <p class="kc-page-subtitle">Complete your profile to unlock loan applications</p>
  </x-slot>

  {{-- Profile completeness bar --}}
  <div class="kc-card mb-6">
    <div class="flex items-center justify-between mb-2">
      <span class="text-sm font-semibold text-kc-navy">Profile Completeness</span>
      <span class="text-sm font-bold text-kc-gold">{{ $profileStatus['percentage'] }}%</span>
    </div>
    <div class="w-full h-2 bg-kc-silver-light rounded-full overflow-hidden">
      <div class="h-full bg-kc-gold rounded-full transition-all duration-500"
           style="width:{{ $profileStatus['percentage'] }}%"></div>
    </div>
    @if(!empty($profileStatus['missing']))
    <div class="mt-3 flex flex-wrap gap-2">
      @foreach($profileStatus['missing'] as $item)
        <span class="kc-badge kc-badge-red text-[10px]">{{ ucfirst(str_replace('_', ' ', $item)) }} missing</span>
      @endforeach
    </div>
    @else
    <p class="text-xs text-emerald-600 font-semibold mt-2">Profile complete — you can apply for a loan.</p>
    @endif
  </div>

  {{-- Pre-qualification widget --}}
  @if($affordabilityResult['eligible'] ?? false)
  <div class="kc-card-navy mb-6 relative overflow-hidden">
    <div class="absolute -top-8 -right-8 w-40 h-40 bg-kc-gold opacity-10 rounded-full blur-2xl pointer-events-none"></div>
    <div class="relative z-10">
      <p class="text-white/60 text-xs uppercase tracking-widest mb-1">Pre-Qualification</p>
      <h3 class="font-display text-2xl font-bold text-white">You qualify for up to</h3>
      <p class="font-display text-4xl font-bold text-kc-gold mt-1">
        R {{ number_format($affordabilityResult['max_instalment'] * 1, 2) }}
        <span class="text-base font-normal text-white/50">per month</span>
      </p>
      <p class="text-white/40 text-xs mt-2">
        Disposable income: R{{ number_format($affordabilityResult['disposable_income'], 2) }} ·
        Max instalment (30%): R{{ number_format($affordabilityResult['max_instalment'], 2) }}
      </p>
      <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary mt-4 inline-flex">Apply Now</a>
    </div>
  </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- LEFT: Income & Expenses form --}}
    <div class="lg:col-span-2 space-y-6">

      {{-- Employment & income --}}
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Employment & Income</h4>
        <form method="POST" action="{{ route('customer-profile.save-affordability') }}" class="space-y-4">
          @csrf
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="kc-label">Employment Type</label>
              <select name="employment_type" class="kc-select" required>
                <option value="">Select...</option>
                @foreach(['permanent'=>'Permanently Employed','contract'=>'Contract','self_employed'=>'Self-Employed','unemployed'=>'Unemployed'] as $v=>$l)
                  <option value="{{ $v }}" {{ ($profile->employment_type ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="kc-label">Employment Tenure</label>
              <select name="employment_tenure" class="kc-select" required>
                <option value="">Select...</option>
                @foreach(['less_than_6m'=>'Less than 6 months','6m_to_1y'=>'6–12 months','1y_to_3y'=>'1–3 years','over_3y'=>'3+ years'] as $v=>$l)
                  <option value="{{ $v }}" {{ ($profile->employment_tenure ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="kc-label">Employer Name</label>
              <input type="text" name="employer_name" value="{{ $profile->employer_name }}" class="kc-input" placeholder="Company name">
            </div>
            <div>
              <label class="kc-label">Net Monthly Income (R)</label>
              <input type="number" name="net_monthly_income" value="{{ $profile->net_monthly_income }}" class="kc-input" required min="0" step="0.01" placeholder="Take-home pay">
            </div>
            <div>
              <label class="kc-label">Gross Monthly Income (R) <span class="text-kc-charcoal/40 text-[10px]">optional</span></label>
              <input type="number" name="gross_monthly_income" value="{{ $profile->gross_monthly_income }}" class="kc-input" min="0" step="0.01" placeholder="Before deductions">
            </div>
            <div>
              <label class="kc-label">Other Income (R) <span class="text-kc-charcoal/40 text-[10px]">optional</span></label>
              <input type="number" name="other_income" value="{{ $profile->other_income ?? 0 }}" class="kc-input" min="0" step="0.01" placeholder="Rental, freelance, etc.">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div>
              <label class="kc-label">Marital Status</label>
              <select name="marital_status" class="kc-select">
                <option value="">Select...</option>
                @foreach(['single'=>'Single','married'=>'Married','divorced'=>'Divorced','widowed'=>'Widowed'] as $v=>$l)
                  <option value="{{ $v }}" {{ ($profile->marital_status ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="kc-label">Dependants</label>
              <input type="number" name="dependants" value="{{ $profile->dependants ?? 0 }}" class="kc-input" min="0" max="20">
            </div>
          </div>

          {{-- Monthly expenses — sliders --}}
          <h5 class="font-semibold text-kc-navy mt-2 pt-2 border-t border-kc-silver-light">Monthly Expenses</h5>
          <p class="text-xs text-kc-charcoal/50 -mt-2 mb-3">Estimates are fine — the system uses these for your affordability calculation (NCA s.81).</p>

          @php
          $expenses = [
            'expense_housing'       => ['Housing / Rent / Bond', 0, 15000],
            'expense_transport'     => ['Transport / Car / Taxi', 0, 8000],
            'expense_existing_debt' => ['Existing Loan Repayments', 0, 10000],
            'expense_insurance'     => ['Insurance & Medical Aid', 0, 5000],
            'expense_living'        => ['Food, Utilities & Phone', 0, 8000],
          ];
          @endphp

          <div x-data="{
            @foreach($expenses as $key => [$label, $min, $max])
              {{ $key }}: {{ $profile->$key ?? 0 }},
            @endforeach
          }" class="space-y-4">
            @foreach($expenses as $key => [$label, $min, $max])
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="kc-label mb-0">{{ $label }}</label>
                <span class="text-sm font-semibold text-kc-navy">R <span x-text="{{ $key }}.toLocaleString('en-ZA',{minimumFractionDigits:0})"></span></span>
              </div>
              <input type="range" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" step="50"
                x-model="{{ $key }}"
                class="w-full h-1.5 appearance-none bg-kc-silver-light rounded-full cursor-pointer accent-kc-gold">
              <input type="hidden" name="{{ $key }}_hidden" :value="{{ $key }}">
            </div>
            @endforeach

            {{-- Override hidden values with range values on submit --}}
            <script>
              document.querySelector('form').addEventListener('submit', function(e) {
                @foreach($expenses as $key => [$label, $min, $max])
                  document.querySelector('[name="{{ $key }}_hidden"]').name = '{{ $key }}';
                  document.querySelector('[name="{{ $key }}"]').name = '{{ $key }}_range';
                @endforeach
              });
            </script>
          </div>

          <button type="submit" class="kc-btn-primary mt-2">Save Financial Profile</button>
        </form>
      </div>

      {{-- Bank statement analysis results --}}
      @if($profile->bank_analysis_run_at)
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">
          Bank Statement Analysis
          <span class="text-xs font-normal text-kc-charcoal/40 ml-2">Run {{ $profile->bank_analysis_run_at->diffForHumans() }}</span>
        </h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
          <div class="text-center">
            <p class="font-display text-2xl font-bold {{ ($profile->avg_days_to_zero ?? 99) < 7 ? 'text-red-600' : 'text-kc-navy' }}">
              {{ $profile->avg_days_to_zero ?? '—' }}
            </p>
            <p class="text-xs text-kc-charcoal/50">Days to R500 after payday</p>
          </div>
          <div class="text-center">
            <p class="font-display text-2xl font-bold text-kc-navy">{{ $profile->avg_days_to_low ?? '—' }}</p>
            <p class="text-xs text-kc-charcoal/50">Days to &lt;20% balance</p>
          </div>
          <div class="text-center">
            <p class="font-display text-2xl font-bold text-kc-navy">
              R {{ number_format($profile->avg_daily_spend_post_payday ?? 0, 0) }}
            </p>
            <p class="text-xs text-kc-charcoal/50">Avg daily spend (7d post-pay)</p>
          </div>
          <div class="text-center">
            <p class="font-display text-2xl font-bold text-kc-navy">
              R {{ number_format($profile->verified_income_amount ?? 0, 0) }}
            </p>
            <p class="text-xs text-kc-charcoal/50">Verified salary</p>
          </div>
        </div>
        @if($profile->bank_statement_risk_flag)
        @php
          $rfClass = ['low'=>'kc-badge-green','medium'=>'kc-badge-gold','high'=>'kc-badge-red','very_high'=>'kc-badge-red'][$profile->bank_statement_risk_flag] ?? 'kc-badge-silver';
        @endphp
        <div class="flex items-start gap-2 p-3 rounded-lg border border-kc-silver-light">
          <span class="kc-badge {{ $rfClass }} mt-0.5">{{ strtoupper(str_replace('_',' ',$profile->bank_statement_risk_flag)) }}</span>
          <p class="text-xs text-kc-charcoal/70">{{ $profile->bank_statement_risk_reason }}</p>
        </div>
        @endif
      </div>
      @endif
    </div>

    {{-- RIGHT: Documents --}}
    <div class="space-y-4">
      <div class="kc-card">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-display font-semibold text-kc-navy">Bank Account</h4>
          <a href="{{ route('accountdetails.create') }}" class="text-xs text-kc-gold hover:underline font-medium">
            + Add account
          </a>
        </div>

        @if($accountDetails->isEmpty())
          <p class="text-[10px] text-kc-charcoal/30 uppercase">Missing</p>
        @else
          <div class="space-y-2">
            @foreach($accountDetails as $account)
              <div class="flex items-center justify-between py-2 border-b border-kc-silver-light/60 last:border-0">
                <div class="flex items-center gap-2 min-w-0">
                  <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                  </div>
                  <span class="text-xs font-medium text-kc-charcoal/70 truncate">
                    {{ $account->bank_name }} — •••• {{ substr($account->account_number, -4) }}
                  </span>
                </div>
                <a href="{{ route('accountdetails.edit', $account) }}" class="text-xs text-kc-gold hover:underline flex-shrink-0 ml-2">Edit</a>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">KYC Documents</h4>
        <form method="POST" action="{{ route('customer-profile.upload-document') }}" enctype="multipart/form-data" class="space-y-4">
          @csrf
          <div>
            <label class="kc-label">Document Type</label>
            <select name="document_type" class="kc-select" required>
              @foreach(\App\Models\CustomerDocument::TYPES as $v => $l)
                <option value="{{ $v }}">{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="kc-label">Document Date</label>
            <input type="date" name="document_date" class="kc-input" max="{{ now()->toDateString() }}">
          </div>
          <div>
            <label class="kc-label">File <span class="text-kc-charcoal/40 text-[10px]">PDF, JPG, PNG or CSV (for statements)</span></label>
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.csv" required class="kc-input py-2 cursor-pointer">
          </div>
          <button type="submit" class="kc-btn-primary w-full justify-center">Upload</button>
        </form>

        <div class="mt-5 space-y-2">
          @foreach(\App\Models\CustomerDocument::TYPES as $type => $label)
          @php $docsOfType = $documents[$type] ?? collect(); @endphp

          @if($type === 'bank_statement')
            @php $hasVerified = $docsOfType->contains('verified', true); @endphp
            <div class="py-2 border-b border-kc-silver-light/60 last:border-0">
              <div class="flex items-center gap-2 mb-1.5">
                @if($hasVerified)
                  <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                  </div>
                @elseif($docsOfType->isNotEmpty())
                  <div class="w-5 h-5 rounded-full bg-kc-gold/15 flex items-center justify-center flex-shrink-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
                  </div>
                @else
                  <div class="w-5 h-5 rounded-full bg-kc-silver-light flex items-center justify-center flex-shrink-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-kc-silver"></div>
                  </div>
                @endif
                <span class="text-xs font-medium text-kc-charcoal/70">{{ $label }}</span>
                <span class="text-[10px] text-kc-charcoal/30">— upload one per bank</span>
              </div>
              @if($docsOfType->isEmpty())
                <p class="text-[10px] text-kc-charcoal/30 uppercase pl-7">Missing</p>
              @else
                <div class="pl-7 space-y-1">
                  @foreach($docsOfType as $doc)
                    <div class="flex items-center justify-between">
                      <span class="text-[11px] text-kc-charcoal/60 truncate">
                        Uploaded {{ $doc->created_at->format('d M Y') }} — {{ $doc->original_name }}
                        <span class="{{ $doc->verified ? 'text-emerald-600' : 'text-kc-gold-muted' }} font-medium">{{ $doc->verified ? '· Verified' : '· Pending review' }}</span>
                      </span>
                      <a href="{{ route('secure-documents.customer-document', $doc) }}" target="_blank" class="text-xs text-kc-gold hover:underline flex-shrink-0 ml-2">View</a>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          @else
            @php $doc = $docsOfType->first(); @endphp
            <div class="flex items-center justify-between py-2 border-b border-kc-silver-light/60 last:border-0">
              <div class="flex items-center gap-2">
                @if($doc?->verified)
                  <div class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                  </div>
                @elseif($doc)
                  <div class="w-5 h-5 rounded-full bg-kc-gold/15 flex items-center justify-center flex-shrink-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
                  </div>
                @else
                  <div class="w-5 h-5 rounded-full bg-kc-silver-light flex items-center justify-center flex-shrink-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-kc-silver"></div>
                  </div>
                @endif
                <span class="text-xs font-medium text-kc-charcoal/70">{{ $label }}</span>
              </div>
              @if($doc)
                <div class="flex items-center gap-2">
                  @unless($doc->verified)
                    <span class="text-[10px] text-kc-gold-muted uppercase">Pending review</span>
                  @endunless
                  <a href="{{ route('secure-documents.customer-document', $doc) }}" target="_blank" class="text-xs text-kc-gold hover:underline">View</a>
                </div>
              @else
                <span class="text-[10px] text-kc-charcoal/30 uppercase">Missing</span>
              @endif
            </div>
          @endif
          @endforeach
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
