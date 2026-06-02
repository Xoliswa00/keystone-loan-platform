<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Keystone Capital Partners — Capital. Partnership. Growth.">
    <meta name="author" content="Keystone Capital Partners">

    <title>@yield('title', config('app.name', 'Keystone Capital Partners'))</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">

    <!-- Open Graph -->
    <meta property="og:site_name" content="Keystone Capital Partners">
    <meta property="og:title" content="Keystone Capital Partners — Lending Platform">
    <meta property="og:description" content="Capital. Partnership. Growth.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Vite assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-kc-white font-sans antialiased text-kc-charcoal">

<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

    {{-- ── Mobile overlay ── --}}
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="kc-overlay"
    ></div>

    {{-- ── Sidebar ── --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="kc-sidebar"
    >
        {{-- Brand --}}
        <div class="kc-sidebar-logo">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                {{-- KC monogram --}}
                <div class="w-9 h-9 flex-shrink-0">
                    <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="36" height="36" rx="6" fill="#C89B3C" fill-opacity="0.15"/>
                        <text x="4" y="26" font-family="Playfair Display, Georgia, serif" font-size="20" font-weight="700" fill="#C89B3C">KC</text>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-display font-semibold text-sm leading-tight">Keystone</p>
                    <p class="text-kc-gold text-[10px] font-medium tracking-widest uppercase leading-none">Capital Partners</p>
                </div>
            </a>
        </div>

        {{-- Navigation --}}
        @include('layouts.navigation')

        {{-- Sidebar footer – user info --}}
        <div class="kc-sidebar-footer">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-kc-gold/20 flex items-center justify-center flex-shrink-0">
                    <span class="text-kc-gold text-xs font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-xs font-medium truncate">{{ Auth::user()->name }}</p>
                    <p class="text-white/40 text-[10px] truncate">{{ Auth::user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                        class="text-white/40 hover:text-white/80 transition-colors p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ── Main content area ── --}}
    <div class="flex-1 flex flex-col min-w-0 lg:ml-64 overflow-y-auto">

        {{-- Top bar --}}
        <header class="kc-topbar sticky top-0 z-20">
            {{-- Mobile sidebar toggle --}}
            <button @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden p-2 -ml-1 rounded-lg text-kc-charcoal/60 hover:bg-kc-silver-light transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Page title slot --}}
            <div class="flex-1 px-4 lg:px-0">
                @isset($header)
                    {{ $header }}
                @endisset
            </div>

            {{-- Right actions --}}
            <div class="flex items-center gap-3">
                {{-- WhatsApp quick link --}}
                <a href="https://wa.me/27721853349" target="_blank"
                    title="WhatsApp Support"
                    class="hidden sm:flex items-center gap-1.5 text-xs font-medium text-kc-charcoal/60 hover:text-emerald-600 transition px-3 py-1.5 rounded-lg hover:bg-emerald-50">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.524 5.845L0 24l6.295-1.507A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.007-1.373l-.36-.213-3.727.892.924-3.618-.235-.372A9.818 9.818 0 1112 21.818z"/>
                    </svg>
                    Support
                </a>

                {{-- User dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-kc-silver-light transition text-sm font-medium text-kc-charcoal">
                        <div class="w-7 h-7 rounded-full bg-kc-navy flex items-center justify-center">
                            <span class="text-kc-gold text-xs font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                        </div>
                        <span class="hidden sm:block">{{ explode(' ', Auth::user()->name)[0] }}</span>
                        <svg class="w-3.5 h-3.5 text-kc-charcoal/50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-kc-card border border-kc-silver-light py-1 z-50">
                        <a href="{{ route('profile.edit') }}"
                            class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-kc-charcoal hover:bg-kc-white transition">
                            <svg class="w-4 h-4 text-kc-silver" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            My Profile
                        </a>
                        <a href="{{ route('accountdetails.index') }}"
                            class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-kc-charcoal hover:bg-kc-white transition">
                            <svg class="w-4 h-4 text-kc-silver" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Bank Account
                        </a>
                        <div class="my-1 border-t border-kc-silver-light"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mx-6 mt-4 kc-alert-success flex items-start gap-2 animate-fadeIn">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 kc-alert-error flex items-start gap-2 animate-fadeIn">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 p-6">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="px-6 py-4 border-t border-kc-silver-light bg-white">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-kc-charcoal/40">
                <span>&copy; {{ date('Y') }} Keystone Capital Partners. All rights reserved.</span>
                <span class="font-display italic text-kc-gold/70">Capital. Partnership. Growth.</span>
            </div>
        </footer>
    </div>
</div>

</body>
</html>
