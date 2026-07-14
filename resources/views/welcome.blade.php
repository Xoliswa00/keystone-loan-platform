@section('title', 'Keystone Capital Partners — Capital. Partnership. Growth.')
@section('description', 'NCR-registered short-term and business loans, decided fast and structured to last. Keystone Capital Partners — built on strong foundations.')

<x-marketing-layout>

    {{-- ── Hero — what we are, in a few words, with the primary conversion CTA ── --}}
    <section class="kc-section-dark">
        <div class="max-w-3xl mx-auto px-6 text-center">
            <div class="flex gap-1.5 mb-8 justify-center" data-aos="fade-up">
                <div class="h-0.5 w-10 bg-kc-gold rounded"></div>
                <div class="h-0.5 w-5 bg-kc-gold/50 rounded"></div>
                <div class="h-0.5 w-2.5 bg-kc-gold/25 rounded"></div>
            </div>

            <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.3em] mb-4" data-aos="fade-up" data-aos-delay="40">
                NCR Registered Credit Provider
            </p>

            <h1 class="kc-display-1 text-white" data-aos="fade-up" data-aos-delay="80">
                Fast, structured loans<br>for people and businesses<br>
                <span class="text-kc-gold">building something real.</span>
            </h1>

            <p class="mt-6 text-white/60 text-base sm:text-lg leading-relaxed max-w-xl mx-auto" data-aos="fade-up" data-aos-delay="140">
                Keystone Capital Partners lends against clear terms, decided quickly,
                and structured so repayment never surprises you.
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-9" data-aos="fade-up" data-aos-delay="200">
                <a href="{{ route('register') }}" class="kc-btn-primary justify-center">Apply for a Loan</a>
                <a href="{{ route('login') }}" class="kc-btn-ghost !border-white/20 !text-white justify-center hover:!bg-white/10">Sign In</a>
            </div>
        </div>
    </section>

    {{-- ── Services — what's on offer, scoped to what this platform actually does ── --}}
    <section class="kc-section-light">
        <div class="max-w-5xl mx-auto px-6">
            <h2 class="kc-display-2 text-center" data-aos="fade-up">What we offer.</h2>
            <p class="mt-4 text-kc-navy/80 text-center max-w-xl mx-auto" data-aos="fade-up" data-aos-delay="60">
                Two structures, one standard: every loan is affordability-checked
                and disclosed in full before you sign.
            </p>

            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="kc-card !p-8" data-aos="fade-up" data-aos-delay="100">
                    <p class="kc-label !mb-2">Personal &amp; Business</p>
                    <h3 class="font-display text-xl font-semibold text-kc-navy">Standard Loan</h3>
                    <p class="text-sm text-kc-navy/80 mt-3 leading-relaxed">
                        A short-term loan payable on your next salary date. Fast decision,
                        clear once-off cost, no surprises at repayment.
                    </p>
                </div>

                <div class="kc-card !p-8" data-aos="fade-up" data-aos-delay="160">
                    <p class="kc-label !mb-2">Personal &amp; Business</p>
                    <h3 class="font-display text-xl font-semibold text-kc-navy">Extended Loan</h3>
                    <p class="text-sm text-kc-navy/80 mt-3 leading-relaxed">
                        A multi-month facility, repaid over up to three instalments, with an
                        enhanced affordability check to make sure the term genuinely fits your budget.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Benefits — the four pillars this brand already stands for ──
         Section is light (only hero/footer stay navy); the pillar list gets
         a navy card-header accent so it still reads as distinct content. ── --}}
    <section class="kc-section-light">
        <div class="max-w-5xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-5 gap-12">
            <div class="lg:col-span-3" data-aos="fade-up">
                <h2 class="kc-display-2">
                    Why Keystone.
                </h2>
                <p class="mt-5 text-kc-navy/80 leading-relaxed max-w-md">
                    We're built the way a keystone holds an arch: every part accountable,
                    the whole structure trusted to hold.
                </p>

                {{-- Sunburst fan motif — same brand symbol used throughout the site,
                     abstracted from the mark's gold fan ornament --}}
                <div class="mt-10 hidden sm:block" aria-hidden="true">
                    <svg width="200" height="115" viewBox="0 0 280 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(-36 140 150)"/>
                        <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(-18 140 150)"/>
                        <path d="M133,150 L147,150 L151,45 L129,45 Z" fill="#D4AD56"/>
                        <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(18 140 150)"/>
                        <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(36 140 150)"/>
                    </svg>
                </div>
            </div>

            <div class="lg:col-span-2" data-aos="fade-up" data-aos-delay="100">
                <div class="kc-card !p-0 overflow-hidden">
                    <div class="bg-kc-navy px-6 py-3">
                        <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.2em]">What holds us together</p>
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

    {{-- ── Closing CTA — the conversion moment ── --}}
    <section class="kc-section-light text-center">
        <div class="max-w-xl mx-auto px-6" data-aos="fade-up">
            <h2 class="kc-display-2">Ready to build on solid ground.</h2>
            <p class="mt-4 text-kc-navy/80">
                Apply in minutes. We'll confirm affordability and get back to you fast.
            </p>
            <a href="{{ route('register') }}" class="kc-btn-primary inline-flex mt-8">Apply for a Loan</a>
            <p class="text-xs text-kc-charcoal/30 mt-6">
                NCR Registered Credit Provider &nbsp;·&nbsp; Keystone Lending (Pty) Ltd
            </p>
        </div>
    </section>

</x-marketing-layout>
