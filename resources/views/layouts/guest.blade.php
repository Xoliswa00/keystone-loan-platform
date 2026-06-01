<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', config('app.name', 'Liger Holding'))</title>
        <meta name="description" content="Liger Holding provides financial aid in times of need." />
    <meta name="author" content="Liger Holding">
    <!-- Open Graph for social sharing -->
    <meta property="og:site_name" content="Liger Holding">
    <meta property="og:title" content="Liger Holding – Financial Services">
    <meta property="og:description" content="Liger Holding provides financial aid in times of need.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('assets/img/android-chrome-192x192.png') }}" >

    <!-- Structured Data (Schema.org) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Liger Holding",
      "url": "{{ url('/') }}",
      "logo": "{{ asset('assets/img/logo.png') }}",
      "sameAs": [
        "https://www.facebook.com/ligerholding",
        "https://twitter.com/ligerholding",
        "https://www.linkedin.com/company/ligerholding"
      ]
    }
    </script>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
        <link rel="img" href="{{ asset('assets/img/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">
    
    <!-- Directly include the compiled CSS -->
<link rel="stylesheet" href="/build/assets/app-DTUn7QGO.css">
<link rel="stylesheet" href="/build/assets/app-DvB2Xm2x.css">

<!-- Include the compiled JS -->
<script type="module" src="/build/assets/app-CKwJ6yXA.js"></script>

    <!-- Scripts -->
   @if (app()->environment('production'))
    <link rel="stylesheet" href="https://cdn.example.com/build/assets/app-DTUn7QGO.css">
    <script type="module" src="https://cdn.example.com/build/assets/app-CKwJ6yXA.js"></script>
@else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif







    <!-- SEO Meta -->
    <meta name="description" content="Liger Holding - a modern way to manage loans and clients efficiently." />
</head>
<body class="font-sans text-gray-900 antialiased bg-white dark:bg-gray-900 transition-all duration-300 flex flex-col min-h-screen">

    <!-- Navigation Bar -->
<nav class="bg-blue-400 dark:bg-blue-800 p-6 rounded-lg  text-white dark:text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
          <a href="{{ url('/') }}" class="text-xl font-bold text-blue-400 hover:text-white transition flex items-center">
    <span class="ml-2 text-white text-lg sm:text-xl font-semibold">Liger Holding</span>
</a>


            <!-- Mobile Hamburger -->
            <div class="sm:hidden">
                <button id="mobile-menu-button" class="text-white focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <!-- Desktop Links -->
            <div class="hidden sm:flex space-x-4 text-sm sm:text-base">
                <a href="{{ route('login') }}" class="hover:text-blue-400">Login</a>
                <a href="{{ route('register') }}" class="hover:text-blue-400">Register</a>
                <a href="{{ route('about') }}" class="hover:text-blue-400">About</a>
                <a href="{{ route('contact') }}" class="hover:text-blue-400">Contact</a>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden sm:hidden px-4 pb-4 space-y-2">
            <a href="{{ route('login') }}" class="block hover:text-blue-400">Login</a>
            <a href="{{ route('register') }}" class="block hover:text-blue-400">Register</a>
            <a href="{{ route('about') }}" class="block hover:text-blue-400">About</a>
            <a href="{{ route('contact') }}" class="block hover:text-blue-400">Contact</a>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="flex-grow flex items-center justify-center px-4 sm:px-6 lg:px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-300 dark:from-gray-900 dark:to-gray-800">
        <div class="w-full max-w-6xl mx-auto">
            {{ $slot }}
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-100 dark:bg-gray-800 text-center text-gray-600 dark:text-gray-400 text-sm py-6 px-4">
        <div>
            &copy; {{ now()->year }} Liger Holding. All rights reserved.
        </div>
        <div class="mt-2 space-x-3">
            <a href="#" class="hover:underline">Privacy Policy</a>
            <a href="#" class="hover:underline">Terms of Service</a>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/27721853349" target="_blank"
       class="fixed bottom-6 right-6 bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-full shadow-lg flex items-center space-x-2 z-50">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path
                d="M20 4a16 16 0 0 0-16 16v1l2.29-2.3A14 14 0 0 1 20 4zm-5.5 9.5l-1.6-.8a1 1 0 0 0-1.1.2l-.5.5a8 8 0 0 1-3.8-3.8l.5-.5a1 1 0 0 0 .2-1.1l-.8-1.6a1 1 0 0 0-1.3-.4c-.9.4-2 1.2-1.9 2.6.3 3.9 3.3 6.9 7.2 7.2 1.4.1 2.2-1 2.6-1.9a1 1 0 0 0-.4-1.3z" />
        </svg>
        <span>Chat</span>
    </a>

    <script>
        document.getElementById('mobile-menu-button').addEventListener('click', function () {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>

</body>
</html>
