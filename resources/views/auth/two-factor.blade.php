@section('title', 'Two-Factor Verification — Keystone Capital Partners')

<x-guest-layout>
  <div class="mb-6 text-center">
    <div class="w-14 h-14 rounded-full bg-kc-navy/8 border border-kc-navy/20 flex items-center justify-center mx-auto mb-4">
      <svg class="w-7 h-7 text-kc-navy" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
      </svg>
    </div>
    <h2 class="font-display text-2xl font-semibold text-kc-navy">Two-Factor Verification</h2>
    <p class="text-sm text-kc-charcoal/50 mt-2">
      We sent a 6-digit code to <strong>{{ $email }}</strong>.<br>
      Enter it below to complete sign-in.
    </p>
  </div>

  <div class="flex items-center gap-3 mb-6">
    <div class="h-px flex-1 bg-kc-gold/30"></div>
    <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
    <div class="h-px w-6 bg-kc-gold/30"></div>
  </div>

  @if(session('success'))
    <div class="kc-alert-success mb-4">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="kc-alert-error mb-4">
      @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-5" x-data="{code:''}" @submit.prevent="if(code.length===6)$el.submit()">
    @csrf
    <div>
      <label class="kc-label text-center block">Verification Code</label>
      <input type="text" name="code" x-model="code"
        class="kc-input text-center text-2xl font-mono tracking-[0.5em] @error('code') border-red-400 @enderror"
        maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
        autofocus autocomplete="one-time-code"
        placeholder="000000" required>
      <x-input-error :messages="$errors->get('code')"/>
    </div>

    <button type="submit" class="kc-btn-primary w-full justify-center"
      :class="code.length < 6 ? 'opacity-60 cursor-not-allowed' : ''"
      :disabled="code.length < 6">
      Verify Identity
    </button>

    <div class="text-center">
      <form method="POST" action="{{ route('two-factor.send') }}" class="inline">
        @csrf
        <button type="submit" class="text-xs text-kc-gold hover:underline">
          Resend code
        </button>
      </form>
      <span class="text-xs text-kc-charcoal/30 mx-2">·</span>
      <form method="POST" action="{{ route('logout') }}" class="inline">
        @csrf
        <button type="submit" class="text-xs text-kc-charcoal/40 hover:underline">Cancel</button>
      </form>
    </div>

    <p class="text-center text-xs text-kc-charcoal/30">Code expires in 10 minutes</p>
  </form>
</x-guest-layout>
