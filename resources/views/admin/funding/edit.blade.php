<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Edit Facility — {{ $fundingFacility->facility_code }}</span>
  </x-slot>

  <div class="kc-card max-w-2xl">
    <form method="POST" action="{{ route('admin.funding.update', $fundingFacility) }}" class="space-y-4">
      @csrf @method('PUT')

      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="kc-label">Facility Name</label>
          <input type="text" name="facility_name" value="{{ old('facility_name',$fundingFacility->facility_name) }}" required class="kc-input">
        </div>
        <div class="col-span-2">
          <label class="kc-label">Funder Name</label>
          <input type="text" name="funder_name" value="{{ old('funder_name',$fundingFacility->funder_name) }}" required class="kc-input">
        </div>
        <div>
          <label class="kc-label">Contact</label>
          <input type="text" name="contact_person" value="{{ old('contact_person',$fundingFacility->contact_person) }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Email</label>
          <input type="email" name="contact_email" value="{{ old('contact_email',$fundingFacility->contact_email) }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Facility Limit (R)</label>
          <input type="number" name="facility_limit" value="{{ old('facility_limit',$fundingFacility->facility_limit) }}" required class="kc-input" step="0.01">
        </div>
        <div>
          <label class="kc-label">Interest Rate (decimal)</label>
          <input type="number" name="interest_rate" value="{{ old('interest_rate',$fundingFacility->interest_rate) }}" required class="kc-input" step="0.0001">
        </div>
        <div>
          <label class="kc-label">Interest Type</label>
          <select name="interest_type" class="kc-select">
            @foreach(['fixed'=>'Fixed','variable'=>'Variable','prime_plus'=>'Prime+'] as $v=>$l)
              <option value="{{ $v }}" {{ $fundingFacility->interest_type===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="kc-label">Payment Frequency</label>
          <select name="payment_frequency" class="kc-select">
            @foreach(['monthly','quarterly','bi-annual','annual','on_demand'] as $f)
              <option value="{{ $f }}" {{ $fundingFacility->payment_frequency===$f?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$f)) }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="kc-label">Payment Day</label>
          <input type="number" name="payment_day" value="{{ $fundingFacility->payment_day }}" min="1" max="31" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Maturity Date</label>
          <input type="date" name="maturity_date" value="{{ $fundingFacility->maturity_date?->toDateString() }}" class="kc-input">
        </div>
        <div>
          <label class="kc-label">Status</label>
          <select name="status" class="kc-select">
            @foreach(['active','paused','fully_repaid','in_default','closed'] as $s)
              <option value="{{ $s }}" {{ $fundingFacility->status===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-span-2">
          <label class="kc-label">Collateral</label>
          <textarea name="collateral_description" rows="2" class="kc-input">{{ $fundingFacility->collateral_description }}</textarea>
        </div>
        <div class="col-span-2">
          <label class="kc-label">Notes</label>
          <textarea name="notes" rows="2" class="kc-input">{{ $fundingFacility->notes }}</textarea>
        </div>
      </div>

      <div class="flex gap-3">
        <a href="{{ route('admin.funding.show', $fundingFacility) }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary flex-1 justify-center">Save Changes</button>
      </div>
    </form>
  </div>
</x-app-layout>
