<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', config('app.name', 'Keystone Capital Partners'))</title>
    <meta name="description" content="Keystone Capital Partners — Capital. Partnership. Growth." />
    <meta name="author" content="Keystone Capital Partners">

    <!-- Open Graph -->
    <meta property="og:site_name" content="Keystone Capital Partners">
    <meta property="og:title" content="Keystone Capital Partners — Lending Platform">
    <meta property="og:description" content="Capital. Partnership. Growth.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">

    <!-- Vite assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-kc-navy overflow-hidden">

    {{-- Full-screen split layout --}}
    <div class="min-h-screen flex">

        {{-- ── LEFT PANEL — Brand / Hero ── --}}
        <div class="hidden lg:flex lg:w-1/2 relative flex-col justify-between p-12 overflow-hidden">

            {{-- Background texture --}}
            <div class="absolute inset-0 bg-kc-navy">
                {{-- Subtle gold radial glow --}}
                <div class="absolute top-0 right-0 w-96 h-96 bg-kc-gold opacity-5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-kc-gold opacity-5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>

                {{-- Sunburst fan — the same brand symbol used on Welcome/About/Contact
                     (abstracted from the mark's gold fan ornament), scaled up as a large
                     watermark so this panel actually tells the brand's story instead of
                     just being a color field. --}}
                <svg class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[140%] max-w-[640px] opacity-[0.10]"
                     viewBox="0 0 280 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMax meet">
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#FFFFFF" fill-opacity="0.5" transform="rotate(-36 140 150)"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#FFFFFF" fill-opacity="0.75" transform="rotate(-18 140 150)"/>
                    <path d="M133,150 L147,150 L151,45 L129,45 Z" fill="#C89B3C"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#FFFFFF" fill-opacity="0.75" transform="rotate(18 140 150)"/>
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#FFFFFF" fill-opacity="0.5" transform="rotate(36 140 150)"/>
                </svg>
            </div>

            {{-- Top — Logo --}}
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
                    <span class="font-display font-bold text-kc-gold text-base">KC</span>
                </div>
                <div>
                    <p class="text-white font-display font-semibold text-base leading-tight">Keystone</p>
                    <p class="text-kc-gold text-[10px] font-medium tracking-[0.2em] uppercase">Capital Partners</p>
                </div>
            </div>

            {{-- Middle — Hero text --}}
            <div class="relative z-10">
                {{-- 3-segment keystone ornament --}}
                <div class="flex gap-1.5 mb-8">
                    <div class="h-0.5 w-8 bg-kc-gold rounded"></div>
                    <div class="h-0.5 w-4 bg-kc-gold/50 rounded"></div>
                    <div class="h-0.5 w-2 bg-kc-gold/25 rounded"></div>
                </div>

                <h1 class="font-display text-4xl xl:text-5xl font-semibold text-white leading-tight">
                    Built on Strong<br>
                    <span class="text-kc-gold">Foundations.</span>
                </h1>
                <p class="mt-5 text-white/60 text-sm leading-relaxed max-w-xs">
                    A keystone holds an arch together — the one piece every other
                    piece depends on. That's how we structure every agreement:
                    individually accountable, built to hold.
                </p>

                {{-- Three pillars --}}
                <div class="mt-10 grid grid-cols-3 gap-4">
                    @foreach(['Capital', 'Partnership', 'Growth'] as $i => $pillar)
                    <div class="text-center">
                        <div class="w-1 h-8 bg-kc-gold/{{ $i === 1 ? '100' : '40' }} rounded mx-auto mb-2"></div>
                        <p class="text-[11px] font-semibold tracking-widest uppercase text-white/{{ $i === 1 ? '90' : '40' }}">{{ $pillar }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Bottom — Tagline --}}
            <div class="relative z-10">
                <p class="text-white/25 text-xs">
                    NCR Registered Credit Provider &nbsp;·&nbsp; Keystone Lending (Pty) Ltd
                </p>
            </div>
        </div>

        {{-- ── RIGHT PANEL — Auth form ── --}}
        <div class="w-full lg:w-1/2 flex flex-col bg-kc-white">

            {{-- Mobile brand bar --}}
            <div class="lg:hidden flex items-center gap-3 px-6 py-5 bg-kc-navy">
                <div class="w-8 h-8 rounded bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
                    <span class="font-display font-bold text-kc-gold text-sm">KC</span>
                </div>
                <div>
                    <p class="text-white font-display font-semibold text-sm">Keystone Capital Partners</p>
                    <p class="text-kc-gold text-[9px] tracking-widest uppercase">Capital. Partnership. Growth.</p>
                </div>
            </div>

            {{-- Form area --}}
            <div class="flex-1 flex items-center justify-center p-8 sm:p-12">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-8 pb-6 text-center">
                <p class="text-xs text-kc-charcoal/30">
                    &copy; {{ date('Y') }} Keystone Capital Partners. All rights reserved.
                </p>
            </div>
        </div>
    </div>

</body>
</html>
