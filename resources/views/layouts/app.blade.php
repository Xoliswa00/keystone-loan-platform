<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="description" content="Liger Holding provides financial aid in times of need." />
    <meta name="author" content="Liger Holding">

    <title>{{ config('app.name', 'Liger Holding') }}</title>

    <!-- Icons and Fonts -->
       <link rel="icon" href="{{ asset('assets/img/favicon.ico') }}">
    <link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
        <link rel="img" href="{{ asset('assets/img/favicon-16x16.png') }}">

    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">
    <!-- Directly include the compiled CSS -->
<link rel="stylesheet" href="/build/assets/app-DTUn7QGO.css">
<link rel="stylesheet" href="/build/assets/app-DvB2Xm2x.css">

<!-- Include the compiled JS -->
<script type="module" src="/build/assets/app-CKwJ6yXA.js"></script>
    <!-- Open Graph for social sharing -->
    <meta property="og:site_name" content="Liger Holding">
    <meta property="og:title" content="Liger Holding – Business Consulting & Services">
    <meta property="og:description" content="Expert business consulting, accounting services, and innovative solutions.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('assets/img/og-image.png') }}">

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


  @vite(['resources/js/app.js', 'resources/css/app.css']) {{-- keep if you use Vite --}}

  <!-- ... -->



    <!-- App Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Mobile UI enhancements */
        @media (max-width: 640px) {
            header, footer, main {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            main > * {
                font-size: 0.875rem;
            }
            input, select, textarea {
                font-size: 0.875rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            h2, h3, h4 {
                font-size: 1.125rem;
            }
        }
    </style>
</head>

<body class="font-sans antialiased overflow-x-hidden">
    <div class="min-h-screen flex flex-col bg-gray-100 relative">
        <!-- Navigation -->
        @include('layouts.navigation')


        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main class="flex-1 bg-white px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 text-center text-sm text-gray-600 dark:text-gray-300 py-6 mt-auto border-t border-gray-200 dark:border-gray-700">
            <div class="container mx-auto px-4">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div>
                        &copy; {{ date('Y') }} {{ config('app.name', 'Liger Holding') }}. All rights reserved.
                    </div>
                    <div class="mt-2 sm:mt-0 space-x-4">
                        <a href="/about" class="hover:text-indigo-600 transition">About</a>
                        <a href="/contact" class="hover:text-indigo-600 transition">Contact</a>
                        <a href="/privacy" class="hover:text-indigo-600 transition">Privacy Policy</a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- WhatsApp Chat Button -->
        <a href="https://wa.me/27721853349" target="_blank" class="fixed bottom-6 right-6 z-50 bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-full shadow-lg flex items-center space-x-2">
            <i class="fab fa-whatsapp text-xl"></i>
            <span class="hidden sm:inline">Chat</span>
        </a>

        <!-- Scroll to Top Button -->
        <button onclick="window.scrollTo({ top: 0, behavior: 'smooth' });" class="fixed bottom-20 right-6 z-40 bg-gray-800 hover:bg-gray-900 text-white p-2 rounded-full shadow-md">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>
    
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>

</body>
</html>
