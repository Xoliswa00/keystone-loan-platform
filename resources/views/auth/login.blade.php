@section('title', 'Sign In — Keystone Capital Partners')

<x-guest-layout>

    {{-- Heading --}}
    <div class="mb-8">
        <h2 class="font-display text-2xl font-semibold text-kc-navy">Welcome back</h2>
        <p class="mt-1 text-sm text-kc-navy/80">Sign in to access your lending dashboard</p>
    </div>

    {{-- Gold divider --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="h-px flex-1 bg-kc-gold/30"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
        <div class="h-px w-6 bg-kc-gold/30"></div>
    </div>

    {{-- Error messages --}}
    @if ($errors->any())
        <div class="kc-alert-error mb-6 flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Session status (e.g. password reset success) --}}
    @if (session('status'))
        <div class="kc-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="kc-label">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="kc-input @error('email') border-red-400 focus:ring-red-300 @enderror"
                placeholder="you@example.com"
            />
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div x-data="{ show: false }">
            <label for="password" class="kc-label">Password</label>
            <div class="relative">
                <input
                    id="password"
                    :type="show ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="kc-input pr-10 @error('password') border-red-400 focus:ring-red-300 @enderror"
                    placeholder="••••••••"
                />
                <button type="button" @click="show = !show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-kc-silver hover:text-kc-charcoal/60 transition">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember + Forgot --}}
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" id="remember_me"
                    class="w-3.5 h-3.5 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30 cursor-pointer">
                <span class="text-xs text-kc-navy/80">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                    class="text-xs text-kc-gold hover:text-kc-gold-muted transition underline underline-offset-2">
                    Forgot password?
                </a>
            @endif
        </div>

        {{-- Submit --}}
        <button type="submit" class="kc-btn-primary w-full justify-center py-3 text-sm mt-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Sign In
        </button>

        {{-- Register link --}}
        @if (Route::has('register'))
            <p class="text-center text-xs text-kc-navy/80 pt-2">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-kc-gold hover:text-kc-gold-muted transition font-medium">
                    Apply now
                </a>
            </p>
        @endif
    </form>

</x-guest-layout>
