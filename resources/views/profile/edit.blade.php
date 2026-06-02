<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Account Settings</span>
    <p class="kc-page-subtitle">Personal details, password, and security</p>
  </x-slot>

  <div class="max-w-2xl space-y-5">

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Personal Information</h4>
      @include('profile.partials.update-profile-information-form')
    </div>

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-2">Change Password</h4>
      <p class="text-xs text-kc-charcoal/50 mb-4">Min 8 characters — must include at least one letter and one number.</p>
      @include('profile.partials.update-password-form')
    </div>

    <div class="kc-card flex items-center justify-between">
      <div>
        <h5 class="font-semibold text-kc-navy">Financial Profile</h5>
        <p class="text-xs text-kc-charcoal/50 mt-0.5">Income, expenses, bank analysis, documents</p>
      </div>
      <a href="{{ route('customer-profile.show') }}" class="kc-btn-ghost text-sm">Manage →</a>
    </div>

    <div class="kc-card flex items-center justify-between">
      <div>
        <h5 class="font-semibold text-kc-navy">Bank Accounts</h5>
        <p class="text-xs text-kc-charcoal/50 mt-0.5">Debit order account for repayments</p>
      </div>
      <a href="{{ route('accountdetails.index') }}" class="kc-btn-ghost text-sm">Manage →</a>
    </div>

    <div class="kc-card border border-red-200">
      <h4 class="font-display font-semibold text-red-600 mb-4">Delete Account</h4>
      @include('profile.partials.delete-user-form')
    </div>

  </div>
</x-app-layout>
