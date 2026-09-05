@section('title', 'Verify Email — Keystone Capital Partners')

<x-guest-layout>
  <div class="mb-6 text-center">
    <div class="w-14 h-14 rounded-full bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center mx-auto mb-4">
      <svg class="w-7 h-7 text-kc-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
      </svg>
    </div>
    <h2 class="font-display text-2xl font-semibold text-kc-navy">Verify Your Email</h2>
    <p class="text-sm text-kc-charcoal/70 mt-2">
      We sent a verification link to your email.<br>Click it to activate your account.
    </p>
  </div>

  @if(session('status') === 'verification-link-sent')
    <div class="kc-alert-success mb-4">A new verification link has been sent to your email address.</div>
  @endif

  <div class="flex flex-col gap-3">
    <form method="POST" action="{{ route('verification.send') }}">
      @csrf
      <button type="submit" class="kc-btn-primary w-full justify-center">Resend Verification Email</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="kc-btn-ghost w-full justify-center text-sm">Sign Out</button>
    </form>
  </div>
</x-guest-layout>
