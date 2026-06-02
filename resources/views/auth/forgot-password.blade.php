@section('title', 'Reset Password — Keystone Capital Partners')

<x-guest-layout>
  <div class="mb-6">
    <h2 class="font-display text-2xl font-semibold text-kc-navy">Reset Password</h2>
    <p class="text-sm text-kc-charcoal/50 mt-1">Enter your email and we'll send a reset link.</p>
  </div>

  <div class="flex items-center gap-3 mb-6">
    <div class="h-px flex-1 bg-kc-gold/30"></div>
    <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
    <div class="h-px w-6 bg-kc-gold/30"></div>
  </div>

  @if(session('status'))
    <div class="kc-alert-success mb-4">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
    @csrf
    <div>
      <label class="kc-label">Email Address</label>
      <input type="email" name="email" value="{{ old('email') }}" required autofocus
        class="kc-input @error('email') border-red-400 @enderror" placeholder="your@email.com">
      <x-input-error :messages="$errors->get('email')"/>
    </div>
    <button type="submit" class="kc-btn-primary w-full justify-center">Send Reset Link</button>
    <p class="text-center text-xs text-kc-charcoal/50">
      <a href="{{ route('login') }}" class="text-kc-gold hover:underline">Back to Sign In</a>
    </p>
  </form>
</x-guest-layout>
