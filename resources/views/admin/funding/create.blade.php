<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">New Funding Facility</span>
    <p class="kc-page-subtitle">Register a new investor or lender account</p>
  </x-slot>

  <form method="POST" action="{{ route('admin.funding.store') }}" enctype="multipart/form-data"
    class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @csrf

    {{-- Funder details --}}
    <div class="kc-card space-y-4">
      <h4 class="font-display font-semibold text-kc-navy">Funder Details</h4>

      <div>
        <label class="kc-label">Facility Name <span class="text-red-500">*</span></label>
        <input type="text" name="facility_name" value="{{ old('facility_name') }}" required class="kc-input" placeholder="e.g. Partner Loan — J Smith">
        @error('facility_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="kc-label">Funder Type <span class="text-red-500">*</span></label>
        <select name="funder_type" required class="kc-select">
          @foreach(\App\Models\FundingFacility::FUNDER_TYPES as $v => $l)
            <option value="{{ $v }}" {{ old('funder_type') === $v ? 'selected' : '' }}>{{ $l }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="kc-label">Legal Name of Funder <span class="text-red-500">*</span></label>
        <input type="text" name="funder_name" value="{{ old('funder_name') }}" required class="kc-input" placeholder="Full legal name / company name">
        @error('funder_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="kc-label">SA ID / Company Reg</label>
          <input type="text" name="funder_id_or_reg" value="{{ old('funder_id_or_reg') }}" class="kc-input font-mono" placeholder="ID or Reg number">
        </div>
        <div>
          <label class="kc-label">Contact Person</label>
          <input type="text" name="contact_person" value="{{ old('contact_person') }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Email</label>
          <input type="email" name="contact_email" value="{{ old('contact_email') }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Phone</label>
          <input type="text" name="contact_phone" value="{{ old('contact_phone') }}" class="kc-input">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="kc-label">Bank Name</label>
          <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="kc-input" placeholder="Funder's bank">
        </div>
        <div>
          <label class="kc-label">Account Number</label>
          <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}" class="kc-input font-mono">
        </div>
      </div>
    </div>

    {{-- Facility terms --}}
    <div class="kc-card space-y-4">
      <h4 class="font-display font-semibold text-kc-navy">Facility Terms</h4>

      <div>
        <label class="kc-label">Facility Limit (R) <span class="text-red-500">*</span></label>
        <input type="number" name="facility_limit" value="{{ old('facility_limit') }}" required min="1" step="0.01" class="kc-input" placeholder="Maximum amount available">
        @error('facility_limit')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="kc-label">Interest Rate (p.a.) <span class="text-red-500">*</span></label>
          <div class="relative">
            <input type="number" name="interest_rate" value="{{ old('interest_rate',0.12) }}" required min="0" max="1" step="0.0001" class="kc-input pr-12" placeholder="0.1200">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-kc-charcoal/40">decimal</span>
          </div>
          <p class="text-[10px] text-kc-charcoal/40 mt-0.5">e.g. 0.1200 = 12% per annum</p>
          @error('interest_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
          <label class="kc-label">Interest Type</label>
          <select name="interest_type" class="kc-select">
            <option value="fixed">Fixed</option>
            <option value="variable">Variable</option>
            <option value="prime_plus">Prime + Margin</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="kc-label">Payment Frequency</label>
          <select name="payment_frequency" class="kc-select">
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="bi-annual">Bi-annual</option>
            <option value="annual">Annual</option>
            <option value="on_demand">On Demand</option>
          </select>
        </div>
        <div>
          <label class="kc-label">Payment Day of Month</label>
          <input type="number" name="payment_day" value="{{ old('payment_day') }}" min="1" max="31" class="kc-input" placeholder="e.g. 25">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="kc-label">Facility Start Date</label>
          <input type="date" name="facility_start_date" value="{{ old('facility_start_date', now()->toDateString()) }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Maturity Date <span class="text-kc-charcoal/40 text-[10px]">(blank = revolving)</span></label>
          <input type="date" name="maturity_date" value="{{ old('maturity_date') }}" class="kc-input">
        </div>
      </div>

      <div>
        <label class="kc-label">Collateral / Security</label>
        <textarea name="collateral_description" rows="2" class="kc-input" placeholder="Describe any collateral or security pledged">{{ old('collateral_description') }}</textarea>
      </div>

      <div>
        <label class="kc-label">Notes</label>
        <textarea name="notes" rows="2" class="kc-input" placeholder="Any additional terms or notes">{{ old('notes') }}</textarea>
      </div>

      <div>
        <label class="kc-label">Facility Agreement (PDF) <span class="text-kc-charcoal/40 text-[10px]">optional</span></label>
        <input type="file" name="agreement_document" accept=".pdf" class="kc-input py-2 cursor-pointer">
      </div>

      <div class="flex gap-3 pt-2">
        <a href="{{ route('admin.funding.index') }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary flex-1 justify-center">Create Facility</button>
      </div>
    </div>
  </form>
</x-app-layout>
