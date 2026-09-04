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
      <p class="text-xs text-kc-charcoal/60 mb-4">Min 8 characters — must include at least one letter and one number.</p>
      @include('profile.partials.update-password-form')
    </div>

    <div class="kc-card flex items-center justify-between">
      <div>
        <h5 class="font-semibold text-kc-navy">Financial Profile</h5>
        <p class="text-xs text-kc-charcoal/60 mt-0.5">Income, expenses, bank analysis, documents</p>
      </div>
      <a href="{{ route('customer-profile.show') }}" class="kc-btn-ghost text-sm">Manage →</a>
    </div>

    <div class="kc-card flex items-center justify-between">
      <div>
        <h5 class="font-semibold text-kc-navy">Bank Accounts</h5>
        <p class="text-xs text-kc-charcoal/60 mt-0.5">Debit order account for repayments</p>
      </div>
      <a href="{{ route('accountdetails.index') }}" class="kc-btn-ghost text-sm">Manage →</a>
    </div>

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-1">Privacy & Consent</h4>
      <p class="text-xs text-kc-charcoal/60 mb-4">
        Under POPIA you may view or withdraw consent for how we process your information at any time.
        Read our <a href="{{ route('privacy') }}" class="text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted" target="_blank">Privacy Policy</a>.
      </p>

      @if ($hasActiveLoan)
        <div class="mb-4 p-2.5 rounded-lg bg-kc-gold/10 border border-kc-gold/30 text-xs text-kc-navy/80">
            You have an active loan, so withdrawal is disabled below — we're required to keep processing your
            information to service that loan and report to credit bureaux under the National Credit Act.
            Contact us if you wish to settle your loan and withdraw consent.
        </div>
      @endif

      <div class="space-y-3">
        @foreach ([
            'data_processing' => 'Data processing for credit assessment',
            'credit_bureau_check' => 'Credit bureau checks & reporting',
        ] as $type => $label)
          <div class="flex items-center justify-between gap-3 {{ ! $loop->first ? 'pt-3 border-t border-kc-silver-light' : '' }}">
            <div>
              <p class="text-sm font-medium text-kc-navy">{{ $label }}</p>
              <p class="text-xs text-kc-charcoal/60">
                  Status:
                  <span class="{{ $consents[$type] ? 'text-green-600' : 'text-red-600' }} font-semibold">
                      {{ $consents[$type] ? 'Granted' : 'Withdrawn' }}
                  </span>
              </p>
            </div>
            @if ($consents[$type] && $hasActiveLoan)
              <button type="button" disabled class="kc-btn-ghost text-xs opacity-40 cursor-not-allowed">Withdraw</button>
            @else
              <form method="POST" action="{{ route('profile.consent.update') }}"
                  onsubmit="return confirm('{{ $consents[$type] ? 'Withdrawing this consent may prevent us from assessing new or existing loan applications. Continue?' : 'Re-grant this consent?' }}');">
                @csrf
                <input type="hidden" name="consent_type" value="{{ $type }}">
                <input type="hidden" name="granted" value="{{ $consents[$type] ? '0' : '1' }}">
                <button type="submit" class="kc-btn-ghost text-xs">
                    {{ $consents[$type] ? 'Withdraw' : 'Grant' }}
                </button>
              </form>
            @endif
          </div>
        @endforeach
      </div>
    </div>

    <div class="kc-card border border-red-200">
      <h4 class="font-display font-semibold text-red-600 mb-4">Delete Account</h4>
      @include('profile.partials.delete-user-form')
    </div>

  </div>
</x-app-layout>
