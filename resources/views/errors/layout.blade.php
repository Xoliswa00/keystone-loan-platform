<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') — Keystone Capital Partners</title>
  @vite(['resources/css/app.css'])
</head>
<body class="bg-kc-navy font-sans antialiased min-h-screen flex items-center justify-center">
  <div class="text-center px-6 py-12 max-w-md mx-auto">
    <div class="mb-6">
      <p class="font-display font-bold text-kc-gold text-base tracking-widest uppercase">Keystone Capital Partners</p>
    </div>

    <h1 class="font-display text-8xl font-bold text-white/10 mb-2">@yield('code')</h1>
    <h2 class="font-display text-2xl font-semibold text-white mb-3">@yield('heading')</h2>
    <p class="text-white/50 text-sm leading-relaxed mb-8">@yield('message')</p>

    <div class="flex gap-3 justify-center">
      <a href="{{ url('/') }}" class="kc-btn-primary">Go Home</a>
      @auth
      <a href="{{ url()->previous() }}" class="kc-btn-ghost text-white/60 border-white/20">Go Back</a>
      @endauth
    </div>

    <p class="text-white/20 text-xs mt-10">Capital. Partnership. Growth.</p>
  </div>
</body>
</html>
