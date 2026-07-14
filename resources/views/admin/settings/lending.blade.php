<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Lending & Risk Settings</span>
    <p class="kc-page-subtitle">Application limits, affordability, CLO risk bands, IFRS 9 provisioning, and reminder/escalation timing — changes apply immediately, no deploy needed</p>
  </x-slot>

  @if(session('success'))
    <div class="kc-alert-success mb-5">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="kc-alert-error mb-5">{{ session('error') }}</div>
  @endif

  <form method="POST" action="{{ route('admin.settings.lending.update') }}">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      {{-- Application Limits --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Application Limits</h4>

        <div>
          <label class="kc-label">Max Concurrent Active Loans</label>
          <input type="number" name="max_active_loans" min="1" max="10"
            value="{{ old('max_active_loans', $settings->max_active_loans) }}"
            required class="kc-input @error('max_active_loans') border-red-400 @enderror">
          @error('max_active_loans')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          <p class="text-[11px] text-kc-charcoal/40 mt-1">A client with this many active (non-settled) loans is blocked from applying for another. Set to 1 for the standard "one loan at a time" rule.</p>
        </div>

        <div>
          <label class="kc-label">Rejection Cooldown (days)</label>
          <input type="number" name="rejection_cooldown_days" min="0" max="365"
            value="{{ old('rejection_cooldown_days', $settings->rejection_cooldown_days) }}"
            required class="kc-input @error('rejection_cooldown_days') border-red-400 @enderror">
          @error('rejection_cooldown_days')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          <p class="text-[11px] text-kc-charcoal/40 mt-1">NCR reckless-lending waiting period after a rejected application before the client may reapply.</p>
        </div>
      </div>

      {{-- Affordability --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Affordability</h4>

        <div>
          <label class="kc-label">Max Instalment as % of Disposable Income</label>
          <input type="number" step="0.01" name="affordability_ratio" min="0.05" max="0.60"
            value="{{ old('affordability_ratio', $settings->affordability_ratio) }}"
            required class="kc-input @error('affordability_ratio') border-red-400 @enderror">
          @error('affordability_ratio')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          <p class="text-[11px] text-kc-charcoal/40 mt-1">As a fraction, e.g. 0.30 = 30%. NCA affordability guideline.</p>
        </div>
      </div>

      {{-- CLO Risk Thresholds --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">CLO Advisory Risk Bands</h4>
        <p class="text-[11px] text-kc-charcoal/40">Risk score (0–100) at which the CLO advisory engine recommends REVIEW or ESCALATE instead of APPROVE.</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="kc-label">Review Threshold</label>
            <input type="number" name="clo_risk_review_threshold" min="0" max="100"
              value="{{ old('clo_risk_review_threshold', $settings->clo_risk_review_threshold) }}"
              required class="kc-input @error('clo_risk_review_threshold') border-red-400 @enderror">
            @error('clo_risk_review_threshold')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Escalate Threshold</label>
            <input type="number" name="clo_risk_escalate_threshold" min="0" max="100"
              value="{{ old('clo_risk_escalate_threshold', $settings->clo_risk_escalate_threshold) }}"
              required class="kc-input @error('clo_risk_escalate_threshold') border-red-400 @enderror">
            @error('clo_risk_escalate_threshold')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      {{-- IFRS 9 Provisioning & Write-off --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">IFRS 9 Provisioning & Write-off</h4>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="kc-label">Stage 2 DPD</label>
            <input type="number" name="ifrs9_stage2_dpd" min="1" max="364"
              value="{{ old('ifrs9_stage2_dpd', $settings->ifrs9_stage2_dpd) }}"
              required class="kc-input @error('ifrs9_stage2_dpd') border-red-400 @enderror">
            @error('ifrs9_stage2_dpd')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Stage 3 DPD</label>
            <input type="number" name="ifrs9_stage3_dpd" min="1" max="364"
              value="{{ old('ifrs9_stage3_dpd', $settings->ifrs9_stage3_dpd) }}"
              required class="kc-input @error('ifrs9_stage3_dpd') border-red-400 @enderror">
            @error('ifrs9_stage3_dpd')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Write-off DPD</label>
            <input type="number" name="ifrs9_writeoff_dpd" min="1" max="1000"
              value="{{ old('ifrs9_writeoff_dpd', $settings->ifrs9_writeoff_dpd) }}"
              required class="kc-input @error('ifrs9_writeoff_dpd') border-red-400 @enderror">
            @error('ifrs9_writeoff_dpd')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="kc-label">Stage 1 Rate</label>
            <input type="number" step="0.01" name="ifrs9_stage1_rate" min="0" max="1"
              value="{{ old('ifrs9_stage1_rate', $settings->ifrs9_stage1_rate) }}"
              required class="kc-input @error('ifrs9_stage1_rate') border-red-400 @enderror">
            @error('ifrs9_stage1_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Stage 2 Rate</label>
            <input type="number" step="0.01" name="ifrs9_stage2_rate" min="0" max="1"
              value="{{ old('ifrs9_stage2_rate', $settings->ifrs9_stage2_rate) }}"
              required class="kc-input @error('ifrs9_stage2_rate') border-red-400 @enderror">
            @error('ifrs9_stage2_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Stage 3 Rate</label>
            <input type="number" step="0.01" name="ifrs9_stage3_rate" min="0" max="1"
              value="{{ old('ifrs9_stage3_rate', $settings->ifrs9_stage3_rate) }}"
              required class="kc-input @error('ifrs9_stage3_rate') border-red-400 @enderror">
            @error('ifrs9_stage3_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
        </div>
        <p class="text-[11px] text-kc-charcoal/40">Rates as fractions, e.g. 0.20 = 20%. Should escalate: Stage 1 &lt; Stage 2 &lt; Stage 3.</p>
      </div>

      {{-- Arrears Escalation --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Arrears Escalation Emails</h4>

        <div>
          <label class="kc-label">Second Notice DPD</label>
          <input type="number" name="arrears_second_notice_dpd" min="1" max="364"
            value="{{ old('arrears_second_notice_dpd', $settings->arrears_second_notice_dpd) }}"
            required class="kc-input @error('arrears_second_notice_dpd') border-red-400 @enderror">
          @error('arrears_second_notice_dpd')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          <p class="text-[11px] text-kc-charcoal/40 mt-1">DPD at which the intermediate "second notice" escalation tier fires — between the Stage 2 and Stage 3 IFRS 9 boundaries above.</p>
        </div>
      </div>

      {{-- Payment Reminders --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Payment Reminders</h4>

        <div>
          <label class="kc-label">Days Before Due Date</label>
          <input type="number" name="payment_reminder_days_before_due" min="1" max="30"
            value="{{ old('payment_reminder_days_before_due', $settings->payment_reminder_days_before_due) }}"
            required class="kc-input @error('payment_reminder_days_before_due') border-red-400 @enderror">
          @error('payment_reminder_days_before_due')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          <p class="text-[11px] text-kc-charcoal/40 mt-1">Default for the daily reminder cron. A manual run with an explicit <code>--days</code> flag still overrides this.</p>
        </div>
      </div>
    </div>

    <div class="mt-6">
      <button type="submit" class="kc-btn-primary">Save Settings</button>
    </div>
  </form>
</x-app-layout>
