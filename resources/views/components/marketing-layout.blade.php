<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'Keystone Capital Partners. Capital. Partnership. Growth.')</title>
    <meta name="description" content="@yield('description', 'Keystone Capital Partners, a strategic capital and partnership firm built on strong foundations, structured growth, and trusted relationships.')" />
    <meta name="author" content="Keystone Capital Partners">

    <meta property="og:site_name" content="Keystone Capital Partners">
    <meta property="og:title" content="@yield('title', 'Keystone Capital Partners')">
    <meta property="og:description" content="Capital. Partnership. Growth.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-kc-white text-kc-charcoal" x-data="{ mobileOpen: false }">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:kc-btn-primary">
        Skip to content
    </a>

    {{-- ── Nav — single line, capped height, sticky ── --}}
    <header class="sticky top-0 z-40 bg-kc-white/95 backdrop-blur-sm border-b border-kc-silver-light">
        <nav class="max-w-6xl mx-auto px-6 h-[72px] flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 shrink-0">
                <div class="w-9 h-9 rounded-lg bg-kc-navy flex items-center justify-center">
                    <span class="font-display font-bold text-kc-gold text-sm">KC</span>
                </div>
                <div class="hidden sm:block leading-tight">
                    <p class="font-display font-semibold text-kc-navy text-sm">Keystone</p>
                    <p class="text-kc-gold text-[9px] font-medium tracking-[0.2em] uppercase">Capital Partners</p>
                </div>
            </a>

            <div class="hidden md:flex items-center gap-8">
                <a href="{{ route('about') }}"
                   class="flex items-center gap-1.5 text-sm font-medium transition-colors {{ request()->routeIs('about') ? 'text-kc-navy' : 'text-kc-navy/70 hover:text-kc-navy' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    About
                </a>
                <a href="{{ route('contact') }}"
                   class="flex items-center gap-1.5 text-sm font-medium transition-colors {{ request()->routeIs('contact') ? 'text-kc-navy' : 'text-kc-navy/70 hover:text-kc-navy' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Contact
                </a>
            </div>

            <div class="hidden md:flex items-center gap-3">
                <a href="{{ route('login') }}" class="kc-btn-ghost !py-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Sign In
                </a>
                <a href="{{ route('register') }}" class="kc-btn-primary !py-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Apply for a Loan
                </a>
            </div>

            <button type="button" class="md:hidden p-2 -mr-2 text-kc-navy" @click="mobileOpen = !mobileOpen" aria-label="Toggle menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </nav>

        <div x-show="mobileOpen" x-cloak x-transition class="md:hidden border-t border-kc-silver-light bg-kc-white px-6 py-5 space-y-4">
            <a href="{{ route('about') }}" class="flex items-center gap-2 text-sm font-medium text-kc-navy/80">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                About
            </a>
            <a href="{{ route('contact') }}" class="flex items-center gap-2 text-sm font-medium text-kc-navy/80">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Contact
            </a>
            <div class="flex flex-col gap-3 pt-2">
                <a href="{{ route('login') }}" class="kc-btn-ghost justify-center">Sign In</a>
                <a href="{{ route('register') }}" class="kc-btn-primary justify-center">Apply for a Loan</a>
            </div>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    {{-- ── Footer ── --}}
    <footer class="bg-kc-navy text-white/70">
        <div class="max-w-6xl mx-auto px-6 py-12 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
                    <span class="font-display font-bold text-kc-gold text-xs">KC</span>
                </div>
                <p class="text-white text-sm font-display font-medium">Keystone Capital Partners</p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                <a href="{{ route('about') }}" class="hover:text-white transition-colors">About</a>
                <a href="{{ route('contact') }}" class="hover:text-white transition-colors">Contact</a>
                <a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms</a>
                <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacy</a>
                <a href="{{ route('login') }}" class="hover:text-white transition-colors">Sign In</a>
            </div>
        </div>

        {{-- Regulatory disclosure. Required to be visible on every public page;
             kept as a plain footer compliance strip rather than dressed up as
             a headline kicker. white/75 on kc-navy = 10.05:1. --}}
        <div class="border-t border-white/10">
            <div class="max-w-6xl mx-auto px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-white/75">
                <p>Keystone Lending (Pty) Ltd &nbsp;·&nbsp; NCR Registered Credit Provider</p>
                <p>&copy; {{ date('Y') }} Keystone Capital Partners. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>
