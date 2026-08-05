@section('title', 'Investor Portal — Keystone Capital Partners')

<x-guest-layout>

    <div class="mb-8">
        <h2 class="font-display text-2xl font-semibold text-kc-navy">Investor Portal</h2>
        <p class="mt-1 text-sm text-kc-navy/80">Enter the ID/registration number and email on file for your facility</p>
    </div>

    <div class="flex items-center gap-3 mb-8">
        <div class="h-px flex-1 bg-kc-gold/30"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
        <div class="h-px w-6 bg-kc-gold/30"></div>
    </div>

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

    <form method="POST" action="{{ route('investor.login.send') }}" class="space-y-5">
        @csrf

        <div>
            <label for="id_number" class="kc-label">ID / registration number</label>
            <input id="id_number" type="text" name="id_number" value="{{ old('id_number') }}" required autofocus
                class="kc-input @error('id_number') border-red-400 @enderror">
        </div>

        <div>
            <label for="email" class="kc-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                class="kc-input @error('email') border-red-400 @enderror" placeholder="you@example.com">
        </div>

        <button type="submit" class="kc-btn-primary w-full justify-center py-3 text-sm mt-2">
            Send access code
        </button>

        <p class="text-center text-xs text-kc-navy/60 pt-2">
            We'll email a one-time code to confirm it's you before showing any facility details.
        </p>
    </form>

</x-guest-layout>
