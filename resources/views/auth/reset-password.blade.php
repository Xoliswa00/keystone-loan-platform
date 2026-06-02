@section('title', 'New Password — Keystone Capital Partners')

<x-guest-layout>
  <div class="mb-6">
    <h2 class="font-display text-2xl font-semibold text-kc-navy">New Password</h2>
    <p class="text-sm text-kc-charcoal/50 mt-1">Choose a strong password for your account.</p>
  </div>

  <form method="POST" action="{{ route('password.store') }}" class="space-y-5" x-data="{show:false}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <div>
      <label class="kc-label">Email</label>
      <input type="email" name="email" value="{{ old('email', $request->email) }}" required class="kc-input @error('email') border-red-400 @enderror">
      <x-input-error :messages="$errors->get('email')"/>
    </div>
    <div>
      <label class="kc-label">New Password</label>
      <div class="relative">
        <input :type="show?'text':'password'" name="password" required autocomplete="new-password"
          class="kc-input pr-10 @error('password') border-red-400 @enderror" placeholder="Min. 8 characters">
        <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-kc-silver hover:text-kc-charcoal/60 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
        </button>
      </div>
      <x-input-error :messages="$errors->get('password')"/>
    </div>
    <div>
      <label class="kc-label">Confirm Password</label>
      <input type="password" name="password_confirmation" required autocomplete="new-password"
        class="kc-input" placeholder="Repeat password">
      <x-input-error :messages="$errors->get('password_confirmation')"/>
    </div>
    <button type="submit" class="kc-btn-primary w-full justify-center">Set New Password</button>
  </form>
</x-guest-layout>
