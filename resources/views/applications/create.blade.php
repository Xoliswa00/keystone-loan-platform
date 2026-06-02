<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">New Loan Application</span>
    <p class="kc-page-subtitle">Complete all fields — your application is reviewed within 1 business day</p>
  </x-slot>

  {{-- Profile status warning --}}
  @if(!($profileStatus['complete'] ?? false))
  <div class="kc-alert-warning mb-6 flex items-start gap-2">
    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <div>
      <p class="font-semibold">Profile {{ $profileStatus['percentage'] ?? 0 }}% complete</p>
      <p class="text-xs mt-0.5">
        Missing: {{ implode(', ', array_map(fn($m)=>ucfirst(str_replace('_',' ',$m)), $profileStatus['missing'] ?? [])) }}
        · <a href="{{ route('customer-profile.show') }}" class="underline font-medium">Complete now →</a>
      </p>
    </div>
  </div>
  @endif

  {{-- Pre-qual widget --}}
  @if(($affordabilityResult['eligible'] ?? false))
  <div class="kc-card-navy mb-6 relative overflow-hidden">
    <div class="absolute -top-6 -right-6 w-32 h-32 bg-kc-gold opacity-10 rounded-full blur-2xl pointer-events-none"></div>
    <div class="relative z-10 flex items-center justify-between">
      <div>
        <p class="text-white/60 text-xs uppercase tracking-widest">Pre-Qualified Limit</p>
        <p class="font-display text-3xl font-bold text-kc-gold mt-1">R {{ number_format($affordabilityResult['max_instalment'] ?? 0, 2) }} <span class="text-sm font-normal text-white/50">/ month</span></p>
        <p class="text-white/40 text-xs mt-1">Disposable: R{{ number_format($affordabilityResult['disposable_income'] ?? 0, 2) }} · 30% threshold</p>
      </div>
    </div>
  </div>
  @endif

  <form method="POST" action="{{ route('loanapplications.store') }}" enctype="multipart/form-data"
    x-data="loanCalc()" class="space-y-6">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      {{-- LEFT: Loan details --}}
      <div class="lg:col-span-2 space-y-5">

        {{-- Product selection --}}
        <div class="kc-card">
          <h4 class="font-display font-semibold text-kc-navy mb-4">Loan Product</h4>
          <div class="grid grid-cols-1 sm:grid-cols-{{ $products->count() > 1 ? '2' : '1' }} gap-3">
            @foreach($products as $product)
            <label class="cursor-pointer">
              <input type="radio" name="loan_product_id" value="{{ $product->id }}"
                x-model="productId"
                @change="updateProduct({{ $product->id }}, {{ $product->min_amount }}, {{ $product->max_amount }}, {{ $product->min_months }}, {{ $product->max_months }}, {{ $product->monthly_interest_rate }})"
                class="sr-only peer" {{ $loop->first ? 'checked' : '' }}>
              <div class="p-4 rounded-xl border-2 border-kc-silver-light peer-checked:border-kc-gold peer-checked:bg-kc-gold/5 transition">
                <p class="font-semibold text-kc-navy">{{ $product->name }}</p>
                <p class="text-xs text-kc-charcoal/50 mt-1">
                  R{{ number_format($product->min_amount,0) }}–R{{ number_format($product->max_amount,0) }} ·
                  {{ $product->min_months }}–{{ $product->max_months }} month{{ $product->max_months > 1 ? 's' : '' }} ·
                  {{ round($product->monthly_interest_rate*100,1) }}% p/m
                </p>
              </div>
            </label>
            @endforeach
          </div>
          @error('loan_product_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Amount & term --}}
        <div class="kc-card">
          <h4 class="font-display font-semibold text-kc-navy mb-4">Loan Amount & Term</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="kc-label">Loan Type</label>
              <select name="loan_type" class="kc-select" required>
                <option value="personal" {{ old('loan_type') === 'personal' ? 'selected' : '' }}>Personal</option>
                <option value="business" {{ old('loan_type') === 'business' ? 'selected' : '' }}>Business</option>
              </select>
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="kc-label mb-0">Amount</label>
                <span class="text-sm font-bold text-kc-navy">R <span x-text="amount.toLocaleString('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2})"></span></span>
              </div>
              <input type="range" name="loan_amount" :min="minAmount" :max="maxAmount" step="50"
                x-model="amount" @input="recalculate()"
                class="w-full h-1.5 appearance-none bg-kc-silver-light rounded-full accent-kc-gold cursor-pointer">
              <div class="flex justify-between text-[10px] text-kc-charcoal/40 mt-0.5">
                <span>R <span x-text="minAmount.toLocaleString()"></span></span>
                <span>R <span x-text="maxAmount.toLocaleString()"></span></span>
              </div>
              <input type="hidden" name="loan_amount" :value="amount">
              @error('loan_amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="maxMonths > 1">
              <label class="kc-label">Repayment Term</label>
              <select name="loan_term_months" x-model="months" @change="recalculate()" class="kc-select">
                <template x-for="m in Array.from({length:maxMonths-minMonths+1},(_,i)=>i+minMonths)" :key="m">
                  <option :value="m" x-text="m + ' month' + (m>1?'s':'')"></option>
                </template>
              </select>
            </div>
            <input x-show="maxMonths === 1" type="hidden" name="loan_term_months" value="1">
            <div>
              <label class="kc-label">Purpose</label>
              <select name="purpose" class="kc-select" required>
                <option value="">Select...</option>
                @foreach(\App\Models\NcrPurposeCode::where('active',true)->get() as $pc)
                  <option value="{{ $pc->code }}">{{ $pc->description }}</option>
                @endforeach
                <option value="Other">Other</option>
              </select>
              @error('purpose')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
          </div>
        </div>

        {{-- Documents --}}
        <div class="kc-card">
          <h4 class="font-display font-semibold text-kc-navy mb-4">Required Documents</h4>
          <p class="text-xs text-kc-charcoal/50 mb-4">Required under NCA s.81 for affordability assessment. PDF, JPG, PNG — max 5MB each.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="kc-label">Latest Payslip <span class="text-red-500">*</span></label>
              <input type="file" name="payslips" accept=".pdf,.jpg,.jpeg,.png" required class="kc-input py-2 cursor-pointer @error('payslips') border-red-400 @enderror">
              @error('payslips')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
              <label class="kc-label">Bank Statement (3 months) <span class="text-red-500">*</span></label>
              <input type="file" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png,.csv" required class="kc-input py-2 cursor-pointer @error('bank_statement') border-red-400 @enderror">
              <p class="text-[10px] text-kc-charcoal/40 mt-0.5">CSV format enables automatic affordability analysis</p>
              @error('bank_statement')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
          </div>
        </div>

        {{-- Terms --}}
        <div class="kc-card">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="terms_conditions" value="1" required
              class="mt-0.5 w-4 h-4 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30 cursor-pointer @error('terms_conditions') border-red-400 @enderror">
            <span class="text-sm text-kc-charcoal/70">
              I confirm all information is accurate. I consent to an affordability assessment and credit bureau check (NCA s.81).
              I have read and accept the <a href="{{ route('terms') }}" target="_blank" class="text-kc-gold hover:underline">Terms & Conditions</a>.
            </span>
          </label>
          @error('terms_conditions')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
      </div>

      {{-- RIGHT: Summary --}}
      <div>
        <div class="kc-card sticky top-20">
          <h4 class="font-display font-semibold text-kc-navy mb-4">Repayment Summary</h4>

          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-kc-charcoal/60">Principal</span><span class="font-semibold">R <span x-text="amount.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span></div>
            <div class="flex justify-between"><span class="text-kc-charcoal/60">Initiation Fee (incl. VAT)</span><span>R <span x-text="initFee.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span></div>
            <div class="flex justify-between"><span class="text-kc-charcoal/60">Service Fee (incl. VAT)</span><span>R <span x-text="serviceFee.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span></div>
            <div class="flex justify-between"><span class="text-kc-charcoal/60">Total Interest (<span x-text="monthlyRate"></span>% p/m)</span><span>R <span x-text="totalInterest.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span></div>
            <div class="border-t border-kc-silver-light pt-3 flex justify-between font-bold">
              <span>Total Repayable</span>
              <span class="text-kc-gold font-display text-base">R <span x-text="totalDue.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span>
            </div>
            <div class="flex justify-between text-kc-navy font-bold">
              <span>Monthly Instalment</span>
              <span class="font-display text-lg">R <span x-text="instalment.toLocaleString('en-ZA',{minimumFractionDigits:2})"></span></span>
            </div>
          </div>

          {{-- Affordability check --}}
          @if($affordabilityResult['eligible'] ?? false)
          <div x-bind:class="instalment <= {{ $affordabilityResult['max_instalment'] }} ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'"
               class="mt-4 p-3 rounded-lg border text-xs transition">
            <p x-show="instalment <= {{ $affordabilityResult['max_instalment'] }}" class="text-emerald-700 font-semibold">
              ✓ Within your affordability limit (R{{ number_format($affordabilityResult['max_instalment'],2) }})
            </p>
            <p x-show="instalment > {{ $affordabilityResult['max_instalment'] }}" class="text-red-600 font-semibold">
              ✗ Exceeds your limit (R{{ number_format($affordabilityResult['max_instalment'],2) }}) — reduce the amount
            </p>
          </div>
          @endif

          {{-- Hidden fields for server-side calculation --}}
          <input type="hidden" name="interest_rate"    :value="monthlyRate/100">
          <input type="hidden" name="initiation_fee"   :value="initFee">
          <input type="hidden" name="service_fee"      :value="serviceFee">
          <input type="hidden" name="total_repayment"  :value="totalDue">

          <button type="submit" class="kc-btn-primary w-full justify-center mt-5">
            Submit Application
          </button>
        </div>
      </div>
    </div>
  </form>
</x-app-layout>

<script>
function loanCalc() {
  return {
    productId: {{ $products->first()?->id ?? 0 }},
    amount: {{ old('loan_amount', $products->first()?->min_amount ?? 500) }},
    months: {{ old('loan_term_months', 1) }},
    minAmount: {{ $products->first()?->min_amount ?? 500 }},
    maxAmount: {{ $products->first()?->max_amount ?? 3000 }},
    minMonths: {{ $products->first()?->min_months ?? 1 }},
    maxMonths: {{ $products->first()?->max_months ?? 1 }},
    monthlyRate: {{ round(($products->first()?->monthly_interest_rate ?? 0.05)*100, 2) }},
    vatRate: 0.15,
    initFeeFlat: {{ $products->first()?->initiation_fee_flat ?? 165 }},
    initFeeRate: {{ $products->first()?->initiation_fee_rate ?? 0.10 }},
    initFeeCap:  {{ $products->first()?->initiation_fee_cap ?? 1207.50 }},
    serviceFeeMo: {{ $products->first()?->monthly_service_fee ?? 69 }},

    get initFee() {
      let base = this.initFeeFlat + this.initFeeRate * Math.max(0, this.amount - 1000);
      return Math.round(Math.min(base, this.initFeeCap) * (1 + this.vatRate) * 100) / 100;
    },
    get serviceFee() {
      return Math.round(this.serviceFeeMo * (1 + this.vatRate) * this.months * 100) / 100;
    },
    get totalInterest() {
      return Math.round(this.amount * (this.monthlyRate/100) * this.months * 100) / 100;
    },
    get totalDue() {
      return Math.round((this.amount + this.totalInterest + this.initFee + this.serviceFee) * 100) / 100;
    },
    get instalment() {
      return Math.round((this.totalDue / this.months) * 100) / 100;
    },

    updateProduct(id, minA, maxA, minM, maxM, rate) {
      this.productId  = id;
      this.minAmount  = minA; this.maxAmount = maxA;
      this.minMonths  = minM; this.maxMonths = maxM;
      this.monthlyRate = Math.round(rate * 100 * 100) / 100;
      this.amount  = Math.max(minA, Math.min(this.amount, maxA));
      this.months  = Math.max(minM, Math.min(this.months, maxM));
    },
    recalculate() {}
  }
}
</script>
