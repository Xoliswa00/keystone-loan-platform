{{-- Shared create/edit form. Expects: $product (existing model or new LoanProduct), $action, $method --}}
<form method="POST" action="{{ $action }}" class="space-y-5">
  @csrf
  @if($method === 'PUT')
    @method('PUT')
  @endif

  @if($errors->has('nca_compliance'))
  <div class="kc-alert-error">
    <p class="font-semibold mb-1">NCA compliance check failed:</p>
    <ul class="list-disc list-inside text-xs space-y-0.5">
      @foreach($errors->get('nca_compliance') as $msg)
        <li>{{ $msg }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="kc-label">Product Name</label>
      <input type="text" name="name" value="{{ old('name', $product->name) }}" required
        class="kc-input @error('name') border-red-400 @enderror">
      @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Code</label>
      <input type="text" name="code" value="{{ old('code', $product->code) }}" required
        class="kc-input @error('code') border-red-400 @enderror" placeholder="e.g. extended">
      @error('code')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
  </div>

  <div>
    <label class="kc-label">Description</label>
    <textarea name="description" rows="2" class="kc-input @error('description') border-red-400 @enderror">{{ old('description', $product->description) }}</textarea>
    @error('description')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div>
      <label class="kc-label">Min Amount (R)</label>
      <input type="number" step="0.01" name="min_amount" value="{{ old('min_amount', $product->min_amount) }}" required
        class="kc-input @error('min_amount') border-red-400 @enderror">
      @error('min_amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Max Amount (R)</label>
      <input type="number" step="0.01" name="max_amount" value="{{ old('max_amount', $product->max_amount) }}" required
        class="kc-input @error('max_amount') border-red-400 @enderror">
      @error('max_amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Min Term (months)</label>
      <input type="number" name="min_months" value="{{ old('min_months', $product->min_months) }}" required
        class="kc-input @error('min_months') border-red-400 @enderror">
      @error('min_months')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Max Term (months)</label>
      <input type="number" name="max_months" value="{{ old('max_months', $product->max_months) }}" required
        class="kc-input @error('max_months') border-red-400 @enderror">
      @error('max_months')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      <p class="text-[11px] text-kc-charcoal/70 mt-1">Set to 1–1 for a single-instalment product, or e.g. 2–5 to allow multi-month terms.</p>
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div>
      <label class="kc-label">Monthly Interest Rate</label>
      <input type="number" step="0.0001" name="monthly_interest_rate" value="{{ old('monthly_interest_rate', $product->monthly_interest_rate) }}" required
        class="kc-input @error('monthly_interest_rate') border-red-400 @enderror">
      @error('monthly_interest_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      <p class="text-[11px] text-kc-charcoal/70 mt-1">As a fraction, e.g. 0.05 = 5%/month. NCA cap: {{ config('nca.interest_rate_cap_monthly') }}.</p>
    </div>
    <div>
      <label class="kc-label">VAT Rate</label>
      <input type="number" step="0.0001" name="vat_rate" value="{{ old('vat_rate', $product->vat_rate) }}" required
        class="kc-input @error('vat_rate') border-red-400 @enderror">
      @error('vat_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Initiation Fee Flat (R)</label>
      <input type="number" step="0.01" name="initiation_fee_flat" value="{{ old('initiation_fee_flat', $product->initiation_fee_flat) }}" required
        class="kc-input @error('initiation_fee_flat') border-red-400 @enderror">
      @error('initiation_fee_flat')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      <p class="text-[11px] text-kc-charcoal/70 mt-1">NCA cap: R{{ config('nca.initiation_fee_flat_cap') }}.</p>
    </div>
    <div>
      <label class="kc-label">Initiation Fee Rate</label>
      <input type="number" step="0.0001" name="initiation_fee_rate" value="{{ old('initiation_fee_rate', $product->initiation_fee_rate) }}" required
        class="kc-input @error('initiation_fee_rate') border-red-400 @enderror">
      @error('initiation_fee_rate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div>
      <label class="kc-label">Initiation Fee Cap (R)</label>
      <input type="number" step="0.01" name="initiation_fee_cap" value="{{ old('initiation_fee_cap', $product->initiation_fee_cap) }}" required
        class="kc-input @error('initiation_fee_cap') border-red-400 @enderror">
      @error('initiation_fee_cap')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      <p class="text-[11px] text-kc-charcoal/70 mt-1">NCA absolute cap: R{{ config('nca.initiation_fee_absolute_cap') }}.</p>
    </div>
    <div>
      <label class="kc-label">Monthly Service Fee (R)</label>
      <input type="number" step="0.01" name="monthly_service_fee" value="{{ old('monthly_service_fee', $product->monthly_service_fee) }}" required
        class="kc-input @error('monthly_service_fee') border-red-400 @enderror">
      @error('monthly_service_fee')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      <p class="text-[11px] text-kc-charcoal/70 mt-1">NCA cap: R{{ config('nca.monthly_service_fee_cap') }}.</p>
    </div>
  </div>

  <div class="flex flex-wrap gap-6">
    <label class="flex items-center gap-2 text-sm text-kc-charcoal/80">
      <input type="checkbox" name="requires_enhanced_affordability" value="1"
        {{ old('requires_enhanced_affordability', $product->requires_enhanced_affordability) ? 'checked' : '' }}
        class="rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
      Requires enhanced affordability check
    </label>
    <label class="flex items-center gap-2 text-sm text-kc-charcoal/80">
      <input type="checkbox" name="active" value="1"
        {{ old('active', $product->active) ? 'checked' : '' }}
        class="rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
      Active — available to all clients
    </label>
  </div>

  <div class="flex gap-3">
    <button type="submit" class="kc-btn-primary">{{ $product->exists ? 'Save Changes' : 'Create Product' }}</button>
    <a href="{{ route('loan-products.index') }}" class="kc-btn-ghost">Cancel</a>
  </div>
</form>
