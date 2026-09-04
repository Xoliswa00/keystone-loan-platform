<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', config('app.name', 'Keystone Capital Partners'))</title>
    <meta name="description" content="Keystone Capital Partners. Capital. Partnership. Growth." />
    <meta name="author" content="Keystone Capital Partners">

    <!-- Open Graph -->
    <meta property="og:site_name" content="Keystone Capital Partners">
    <meta property="og:title" content="@yield('title', 'Keystone Capital Partners')">
    <meta property="og:description" content="Capital. Partnership. Growth.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">

    {{-- Vite assets. resources/js/app.js imports Alpine and calls Alpine.start(),
         so this page must NOT also pull the Alpine CDN build — two copies race
         to initialise the same x-data trees. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- No overflow-hidden here. It used to clamp the whole document: on a 390x667
     viewport the register form's submit button sat 77px below the fold and the
     page could not be scrolled to it at all. --}}
<body class="font-sans antialiased bg-kc-navy">

    <div class="min-h-screen flex">

        {{-- ── LEFT PANEL — brand ────────────────────────────────────────────
             Sticky full-height so a long form on the right scrolls past a
             fixed brand panel rather than dragging it up the screen. ── --}}
        <div class="hidden lg:flex lg:w-1/2 lg:sticky lg:top-0 lg:h-screen relative flex-col justify-between p-12 overflow-hidden bg-kc-navy">

            {{-- Sunburst fan — the brand mark's gold ornament, as a watermark.
                 Anchored bottom-right: centred (as it was) it collided with the
                 pillar row, and bottom-left put it directly behind the
                 regulatory line. This corner is the panel's genuinely empty one. --}}
            <svg class="absolute -bottom-24 -right-20 w-[480px] opacity-[0.09] pointer-events-none"
                 viewBox="0 0 280 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#FFFFFF" fill-opacity="0.5" transform="rotate(-36 140 150)"/>
                <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#FFFFFF" fill-opacity="0.75" transform="rotate(-18 140 150)"/>
                <path d="M133,150 L147,150 L151,45 L129,45 Z" fill="#C89B3C"/>
                <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#FFFFFF" fill-opacity="0.75" transform="rotate(18 140 150)"/>
                <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#FFFFFF" fill-opacity="0.5" transform="rotate(36 140 150)"/>
            </svg>

            {{-- Top — logo --}}
            <a href="{{ url('/') }}" class="relative z-10 flex items-center gap-3 self-start">
                <div class="w-10 h-10 rounded-lg bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
                    <span class="font-display font-bold text-kc-gold text-base">KC</span>
                </div>
                <div>
                    <p class="text-white font-display font-semibold text-base leading-tight">Keystone</p>
                    <p class="text-kc-gold text-[10px] font-medium tracking-[0.2em] uppercase">Capital Partners</p>
                </div>
            </a>

            {{-- Middle — brand statement --}}
            <div class="relative z-10">
                <div class="kc-arch-mark mb-8" aria-hidden="true"></div>

                <h2 class="font-display text-4xl xl:text-5xl font-semibold text-white leading-tight">
                    Built on strong<br>
                    <span class="text-kc-gold">Foundations.</span>
                </h2>
                {{-- white/75 on kc-navy = 10.05:1 --}}
                <p class="mt-5 text-white/75 text-sm leading-relaxed max-w-sm">
                    A keystone is the wedge at the top of an arch. Every other stone
                    leans on it. We structure every agreement the same way:
                    individually accountable, built to hold.
                </p>

                {{-- Three pillars. Classes are written out in full rather than built
                     by string interpolation — an interpolated Tailwind class only
                     survives because the compiled Blade cache happens to be on the
                     scanner's content path, which is not a guarantee to rely on. --}}
                <div class="mt-10 grid grid-cols-3 gap-4 max-w-sm">
                    <div class="text-center">
                        <div class="w-1 h-8 bg-kc-gold/50 rounded mx-auto mb-2.5" aria-hidden="true"></div>
                        <p class="text-[11px] font-semibold tracking-widest uppercase text-white/75">Capital</p>
                    </div>
                    <div class="text-center">
                        <div class="w-1 h-8 bg-kc-gold rounded mx-auto mb-2.5" aria-hidden="true"></div>
                        <p class="text-[11px] font-semibold tracking-widest uppercase text-white">Partnership</p>
                    </div>
                    <div class="text-center">
                        <div class="w-1 h-8 bg-kc-gold/50 rounded mx-auto mb-2.5" aria-hidden="true"></div>
                        <p class="text-[11px] font-semibold tracking-widest uppercase text-white/75">Growth</p>
                    </div>
                </div>
            </div>

            {{-- Bottom — regulatory disclosure. white/75 on kc-navy = 10.05:1
                 (was white/55 at 5.95:1). --}}
            <div class="relative z-10">
                <p class="text-white/75 text-xs">
                    Keystone Lending (Pty) Ltd &nbsp;·&nbsp; NCR Registered Credit Provider
                </p>
            </div>
        </div>

        {{-- ── RIGHT PANEL — auth form ── --}}
        <div class="w-full lg:w-1/2 flex flex-col bg-kc-white">

            {{-- Mobile brand bar --}}
            <a href="{{ url('/') }}" class="lg:hidden flex items-center gap-3 px-6 py-5 bg-kc-navy">
                <div class="w-8 h-8 rounded bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center shrink-0">
                    <span class="font-display font-bold text-kc-gold text-sm">KC</span>
                </div>
                <div>
                    <p class="text-white font-display font-semibold text-sm leading-tight">Keystone Capital Partners</p>
                    <p class="text-kc-gold text-[9px] tracking-widest uppercase">Capital. Partnership. Growth.</p>
                </div>
            </a>

            {{-- Form area --}}
            <div class="flex-1 flex items-center justify-center px-6 py-10 sm:p-12">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>

            {{-- Footer. The regulatory line is repeated here because the brand
                 panel that carries it is hidden below lg. --}}
            <div class="px-6 pb-8 text-center space-y-1">
                <p class="text-xs text-kc-charcoal/70 lg:hidden">
                    Keystone Lending (Pty) Ltd &middot; NCR Registered Credit Provider
                </p>
                <p class="text-xs text-kc-charcoal/70">
                    &copy; {{ date('Y') }} Keystone Capital Partners. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    @include('partials.monitoring-beacon')
</body>
</html>
