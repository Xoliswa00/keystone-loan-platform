<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Company Settings</span>
    <p class="kc-page-subtitle">Legal identity, NCR details, and notification preferences — used on all agreements and emails</p>
  </x-slot>

  @if(!$company->isComplete())
  <div class="kc-alert-warning mb-5 flex items-start gap-2">
    <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
    <div>
      <p class="font-semibold">Company settings incomplete</p>
      <p class="text-xs mt-0.5">NCR number, registration number, and physical address are required for NCA-compliant agreements. All generated documents currently show placeholder values.</p>
    </div>
  </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.company.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      {{-- Legal Identity --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Legal Identity</h4>

        <div>
          <label class="kc-label">Company / Trading Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" value="{{ old('name', $company->name) }}" required class="kc-input">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="kc-label">CIPC Registration No. <span class="text-red-500">*</span></label>
            <input type="text" name="registration_no" value="{{ old('registration_no', $company->registration_no) }}" required class="kc-input font-mono" placeholder="e.g. 2023/123456/07">
          </div>
          <div>
            <label class="kc-label">NCR Registration No. <span class="text-red-500">*</span></label>
            <input type="text" name="ncr_number" value="{{ old('ncr_number', $company->ncr_number) }}" required class="kc-input font-mono" placeholder="e.g. NCRCP1234">
          </div>
          <div>
            <label class="kc-label">VAT Registration No.</label>
            <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}" class="kc-input font-mono" placeholder="e.g. 4680123456">
          </div>
          <div>
            <label class="kc-label">NCR Credit Category</label>
            <select name="ncr_credit_category" class="kc-select" required>
              @foreach(['unsecured'=>'Unsecured Credit','short_term'=>'Short-term Credit','developmental'=>'Developmental Credit','mortgage'=>'Mortgage Agreements'] as $v=>$l)
                <option value="{{ $v }}" {{ old('ncr_credit_category', $company->ncr_credit_category) === $v ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Agreement signatory --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="kc-label">Authorised Signatory <span class="text-red-500">*</span></label>
            <input type="text" name="authorised_signatory" value="{{ old('authorised_signatory', $company->authorised_signatory) }}" required class="kc-input" placeholder="Full name">
          </div>
          <div>
            <label class="kc-label">Title</label>
            <input type="text" name="signatory_title" value="{{ old('signatory_title', $company->signatory_title) }}" class="kc-input" placeholder="Director / Manager">
          </div>
        </div>

        <div>
          <label class="kc-label">Tagline</label>
          <input type="text" name="tagline" value="{{ old('tagline', $company->tagline) }}" class="kc-input" placeholder="Capital. Partnership. Growth.">
        </div>

        {{-- Logo --}}
        <div>
          <label class="kc-label">Company Logo (PNG/JPG/SVG, max 2MB)</label>
          @if($company->logo_path)
            <div class="mb-2">
              <img src="{{ asset('storage/' . $company->logo_path) }}" alt="Logo" class="h-12 object-contain">
            </div>
          @endif
          <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg" class="kc-input py-2 cursor-pointer">
        </div>
      </div>

      {{-- Contact & Location --}}
      <div class="kc-card space-y-4">
        <h4 class="font-display font-semibold text-kc-navy">Contact & Location</h4>

        <div>
          <label class="kc-label">Physical Address <span class="text-red-500">*</span></label>
          <input type="text" name="physical_address" value="{{ old('physical_address', $company->physical_address) }}" required class="kc-input" placeholder="Street address">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="kc-label">City</label>
            <input type="text" name="city" value="{{ old('city', $company->city) }}" class="kc-input">
          </div>
          <div>
            <label class="kc-label">Province</label>
            <select name="province" class="kc-select">
              <option value="">Select...</option>
              @foreach(['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Free State','Limpopo','Mpumalanga','North West','Northern Cape'] as $p)
                <option {{ old('province', $company->province) === $p ? 'selected' : '' }}>{{ $p }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="kc-label">Postal Code</label>
            <input type="text" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" class="kc-input font-mono" maxlength="4" placeholder="0001">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="kc-label">Phone <span class="text-red-500">*</span></label>
            <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" required class="kc-input" placeholder="+27 xx xxx xxxx">
          </div>
          <div>
            <label class="kc-label">WhatsApp</label>
            <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $company->whatsapp_number) }}" class="kc-input font-mono" placeholder="27xxxxxxxxx">
          </div>
          <div>
            <label class="kc-label">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email', $company->email) }}" required class="kc-input">
          </div>
          <div>
            <label class="kc-label">Support Email</label>
            <input type="email" name="support_email" value="{{ old('support_email', $company->support_email) }}" class="kc-input">
          </div>
        </div>

        <div>
          <label class="kc-label">Website</label>
          <input type="url" name="website" value="{{ old('website', $company->website) }}" class="kc-input" placeholder="https://...">
        </div>
      </div>

      {{-- Notification settings --}}
      <div class="kc-card space-y-4 lg:col-span-2">
        <h4 class="font-display font-semibold text-kc-navy">Email Notification Settings</h4>
        <p class="text-xs text-kc-charcoal/60">These settings control how all system emails appear and who receives copies.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label class="kc-label">From Email</label>
            <input type="email" name="notification_from_email" value="{{ old('notification_from_email', $company->notification_from_email) }}" class="kc-input" placeholder="noreply@yourcompany.co.za">
          </div>
          <div>
            <label class="kc-label">From Name</label>
            <input type="text" name="notification_from_name" value="{{ old('notification_from_name', $company->notification_from_name) }}" class="kc-input" placeholder="Keystone Capital Partners">
          </div>
          <div class="sm:col-span-2">
            <label class="kc-label">CC on All Loan Notifications <span class="text-kc-charcoal/60 text-[10px]">comma-separated</span></label>
            <input type="text" name="notification_cc" value="{{ old('notification_cc', $company->notification_cc) }}" class="kc-input" placeholder="manager@company.co.za, compliance@company.co.za">
            <p class="text-[10px] text-kc-charcoal/60 mt-1">All approval, rejection, disbursement and payment notifications will CC these addresses.</p>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit" class="kc-btn-primary">Save Company Settings</button>
        </div>
      </div>
    </div>
  </form>
</x-app-layout>
