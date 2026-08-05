@section('title', 'Verify Access Code — Keystone Capital Partners')

<x-guest-layout>

    <div class="mb-8">
        <h2 class="font-display text-2xl font-semibold text-kc-navy">Enter your code</h2>
        <p class="mt-1 text-sm text-kc-navy/80">Check your email for a 6-digit access code</p>
    </div>

    <div class="flex items-center gap-3 mb-8">
        <div class="h-px flex-1 bg-kc-gold/30"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
        <div class="h-px w-6 bg-kc-gold/30"></div>
    </div>

    @if (session('info'))
        <div class="kc-alert-success mb-6">{{ session('info') }}</div>
    @endif

    @if ($errors->any())
        <div class="kc-alert-error mb-6">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (session('error'))
        <div class="kc-alert-error mb-6">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('investor.verify.submit') }}" class="space-y-5">
        @csrf

        <div>
            <label for="code" class="kc-label">6-digit code</label>
            <input id="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" name="code" required autofocus
                class="kc-input text-center tracking-[0.5em] text-lg @error('code') border-red-400 @enderror">
        </div>

        <button type="submit" class="kc-btn-primary w-full justify-center py-3 text-sm mt-2">
            Verify &amp; continue
        </button>

        <p class="text-center text-xs text-kc-navy/60 pt-2">
            <a href="{{ route('investor.login') }}" class="text-kc-gold hover:text-kc-gold-muted transition underline underline-offset-2">
                Start over
            </a>
        </p>
    </form>

</x-guest-layout>
