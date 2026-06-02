<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Application Not Available</span>
  </x-slot>

  <div class="max-w-lg mx-auto py-8">
    <div class="kc-card text-center">

      {{-- Icon by gate type --}}
      @php
        $icon = match($gate ?? '') {
          'gate:active_loan'          => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          'gate:rejection_cooldown'   => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
          'gate:blacklisted'          => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
          'gate:profile_incomplete'   => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
          default                     => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        };
        $colour = in_array($gate ?? '', ['gate:blacklisted','gate:admin_restriction']) ? 'text-red-500 bg-red-100' : 'text-kc-gold-muted bg-kc-gold/10';
      @endphp

      <div class="w-16 h-16 rounded-full {{ $colour }} flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
        </svg>
      </div>

      <h3 class="font-display text-2xl font-semibold text-kc-navy mb-3">Application Not Available</h3>

      <div class="text-sm text-kc-charcoal/70 leading-relaxed mb-5 px-4">
        {!! nl2br(e($reason ?? 'You cannot submit a loan application at this time.')) !!}
      </div>

      @if($unblock_at ?? false)
      <div class="kc-card-navy inline-block px-6 py-3 mb-5 rounded-xl mx-auto">
        <p class="text-white/60 text-xs uppercase tracking-wider mb-1">You may reapply from</p>
        <p class="font-display text-xl font-bold text-kc-gold">
          {{ \Carbon\Carbon::parse($unblock_at)->format('d F Y') }}
        </p>
        <p class="text-white/40 text-xs mt-1">
          {{ \Carbon\Carbon::parse($unblock_at)->diffForHumans() }}
        </p>
      </div>
      @endif

      {{-- Gate-specific guidance --}}
      @if(($gate ?? '') === 'gate:rejection_cooldown')
      <div class="kc-alert-warning mb-5 text-left text-xs">
        <p class="font-semibold mb-1">Why is there a waiting period?</p>
        <p>This is aligned with NCR guidelines and responsible lending practice. Reapplying immediately after a rejection without a change in financial circumstances would not change the outcome. Use this time to improve your financial profile.</p>
      </div>
      @endif

      @if(($gate ?? '') === 'gate:active_loan')
      <div class="kc-alert-warning mb-5 text-left text-xs">
        <p class="font-semibold mb-1">You have an active loan</p>
        <p>Under our policy, only one loan may be active at a time. This is also required under NCA Section 81 (affordability assessment — existing debt obligations must be settled first).</p>
      </div>
      @endif

      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        @if(($action_url ?? false))
        <a href="{{ $action_url }}" class="kc-btn-primary">Complete Profile</a>
        @endif
        <a href="{{ route('dashboard') }}" class="kc-btn-ghost">Back to Dashboard</a>
        <a href="https://wa.me/27721853349" target="_blank" class="kc-btn-ghost text-emerald-600 border-emerald-200">Contact Support</a>
      </div>
    </div>
  </div>
</x-app-layout>
