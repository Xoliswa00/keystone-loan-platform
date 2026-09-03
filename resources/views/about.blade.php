@section('title', 'About Keystone Capital Partners')
@section('description', 'Keystone Capital Partners is a strategic capital and partnership firm built on strong foundations, structured growth, and trusted relationships.')

<x-marketing-layout>

    {{-- ── Hero — editorial, left-aligned, quietly confident ── --}}
    <section class="kc-section-dark">
        <div class="max-w-3xl mx-auto px-6">
            <div class="flex gap-1.5 mb-8" data-aos="fade-up">
                <div class="h-0.5 w-10 bg-kc-gold rounded"></div>
                <div class="h-0.5 w-5 bg-kc-gold/50 rounded"></div>
                <div class="h-0.5 w-2.5 bg-kc-gold/25 rounded"></div>
            </div>

            <h1 class="kc-display-1 text-white" data-aos="fade-up" data-aos-delay="80">
                Built on strong<br><span class="text-kc-gold">foundations.</span>
            </h1>

            <p class="mt-6 text-white/60 text-base sm:text-lg leading-relaxed max-w-xl" data-aos="fade-up" data-aos-delay="140">
                Keystone Capital Partners is a strategic capital and partnership firm,
                structured for long-term growth and trusted relationships.
            </p>

            <div class="flex flex-col sm:flex-row gap-3 mt-9" data-aos="fade-up" data-aos-delay="200">
                <a href="{{ route('register') }}" class="kc-btn-primary justify-center">Apply for a Loan</a>
                <a href="{{ route('login') }}" class="kc-btn-ghost !border-white/20 !text-white justify-center hover:!bg-white/10">Sign In</a>
            </div>
        </div>
    </section>

    {{-- ── Story — the keystone metaphor, stacked headline + body, own visual ── --}}
    <section class="kc-section-light">
        <div class="max-w-3xl mx-auto px-6">
            <h2 class="kc-display-2" data-aos="fade-up">Three founders. One foundation.</h2>
            <p class="mt-5 text-kc-navy/80 leading-relaxed max-w-2xl" data-aos="fade-up" data-aos-delay="80">
                Keystone was founded on a simple architectural idea. A keystone is the single
                piece that locks an arch together, carrying the weight of everything around it.
                That is how our three founding partners work: individually accountable,
                collectively responsible, built to hold structure under pressure.
            </p>

            {{-- Sunburst fan motif — abstracted from the brand mark's gold fan ornament --}}
            <div class="mt-12 flex justify-center" data-aos="fade-up" data-aos-delay="140">
                <svg width="200" height="115" viewBox="0 0 280 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(-36 140 150)"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(-18 140 150)"/>
                    <path d="M133,150 L147,150 L151,45 L129,45 Z" fill="#D4AD56"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(18 140 150)"/>
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(36 140 150)"/>
                </svg>
            </div>
        </div>
    </section>

    {{-- ── Values — asymmetric: one featured statement + a short list, not equal cards.
         Section is light (only hero/footer stay navy); the value list gets a navy
         card-header accent so it still reads as distinct content. ── --}}
    <section class="kc-section-light">
        <div class="max-w-5xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-5 gap-12">
            <div class="lg:col-span-3" data-aos="fade-up">
                <h2 class="kc-display-2">
                    What holds us together.
                </h2>
                <p class="mt-5 text-kc-navy/80 leading-relaxed max-w-md">
                    We measure every decision against one standard: does it strengthen
                    the foundation, or only speed things up. Structure comes first.
                </p>
            </div>

            <div class="lg:col-span-2" data-aos="fade-up" data-aos-delay="100">
                <div class="kc-card !p-0 overflow-hidden">
                    <div class="bg-kc-navy px-6 py-3">
                        <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.2em]">Our values</p>
                    </div>
                    <div class="divide-y divide-kc-silver-light px-6">
                        @foreach([
                            ['Capital', 'Disciplined funding, deployed with intent.'],
                            ['Partnership', 'Decisions made together, never in isolation.'],
                            ['Trust', 'Every agreement, honoured in full.'],
                            ['Growth', 'Measured in years, not quarters.'],
                        ] as [$word, $line])
                            <div class="py-4">
                                <p class="font-display text-kc-gold-muted font-semibold text-lg">{{ $word }}</p>
                                <p class="text-kc-navy/80 text-sm mt-1">{{ $line }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Closing ── --}}
    <section class="kc-section-light text-center">
        <div class="max-w-xl mx-auto px-6" data-aos="fade-up">
            <h2 class="kc-display-2">Ready to build on solid ground.</h2>
            <a href="{{ route('register') }}" class="kc-btn-primary inline-flex mt-8">Apply for a Loan</a>
            <p class="text-xs text-kc-charcoal/60 mt-6">
                NCR Registered Credit Provider &nbsp;·&nbsp; Keystone Lending (Pty) Ltd
            </p>
        </div>
    </section>

</x-marketing-layout>
