@section('title', 'Keystone Capital Partners — Capital. Partnership. Growth.')

<x-guest-layout>
  <div class="text-center">
    <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.3em] mb-3">Keystone Capital Partners</p>
    <h1 class="font-display text-3xl sm:text-4xl font-bold text-kc-navy leading-tight">
      Built on Strong<br><span class="text-kc-gold">Foundations.</span>
    </h1>
    <p class="text-kc-charcoal/60 mt-4 text-sm leading-relaxed max-w-sm mx-auto">
      A strategic capital and partnership firm focused on structured growth,
      trusted relationships, and long-term value.
    </p>

    <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
      <a href="{{ route('login') }}" class="kc-btn-primary justify-center">Sign In</a>
      <a href="{{ route('register') }}" class="kc-btn-ghost justify-center">Apply for a Loan</a>
    </div>

    <div class="mt-10 grid grid-cols-3 gap-4 max-w-xs mx-auto">
      @foreach(['Capital','Partnership','Growth'] as $pillar)
      <div class="text-center">
        <div class="w-1 h-8 bg-kc-gold/40 rounded mx-auto mb-2"></div>
        <p class="text-[10px] font-semibold tracking-[0.2em] uppercase text-kc-charcoal/50">{{ $pillar }}</p>
      </div>
      @endforeach
    </div>

    <p class="text-xs text-kc-charcoal/30 mt-8">NCR Registered Credit Provider</p>
  </div>
</x-guest-layout>
