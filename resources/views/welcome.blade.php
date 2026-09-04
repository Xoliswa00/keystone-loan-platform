@section('title', 'Keystone Capital Partners — Capital. Partnership. Growth.')
@section('description', 'NCR-registered personal and business loans. Affordability checked, fully costed, and repaid on your salary date. Keystone Capital Partners.')

<x-marketing-layout>

    {{-- ── Hero ──────────────────────────────────────────────────────────────
         Asymmetric split rather than a centred column: the pitch carries the
         left, the arch carries the right. The arch is the brand metaphor drawn
         literally — voussoirs radiating from a single centre (the same wedge
         geometry as the sunburst mark), locked by the gold keystone at the
         apex. It sits in its own grid column as content, not as a backdrop.
         Motion is CSS-only (animate-fadeIn) so copy is never left invisible
         if scripting fails, and app.css already neutralises it under
         prefers-reduced-motion. ── --}}
    <section class="kc-section-dark overflow-hidden">
        <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

            <div class="lg:col-span-7 animate-fadeIn">
                <div class="kc-arch-mark mb-8" aria-hidden="true"></div>

                <h1 class="kc-display-1 xl:text-5xl text-white [text-wrap:balance]">
                    Fast, structured loans for people and businesses
                    {{-- Own line from lg so the gold clause never starts mid-line;
                         below lg it wraps naturally rather than being forced by
                         hard <br>s, which produced very ragged lines at 390px. --}}
                    <span class="text-kc-gold lg:block">building something real.</span>
                </h1>

                <p class="mt-6 text-white/75 text-base sm:text-lg leading-relaxed max-w-xl">
                    Keystone lends on clear terms, decides quickly, and sets every
                    repayment against your salary date so the schedule never catches
                    you out.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 mt-9">
                    <a href="{{ route('register') }}" class="kc-btn-primary justify-center">Apply for a Loan</a>
                    <a href="{{ route('login') }}" class="kc-btn-outline-light justify-center">Sign In</a>
                </div>

                <p class="mt-7 text-xs text-white/75">
                    Keystone Lending (Pty) Ltd is a registered credit provider with the
                    National Credit Regulator.
                </p>
            </div>

            <div class="lg:col-span-5 flex flex-col items-center">
                <svg viewBox="0 0 340 270" class="w-full max-w-[320px] h-auto" fill="none"
                     xmlns="http://www.w3.org/2000/svg" role="img"
                     aria-label="A masonry arch: seven wedge-shaped stones curving to a single gold keystone at the apex.">
                    <g fill="#C89B3C">
                        {{-- Voussoirs, springing from one centre — same construction as the
                             sunburst fan, drawn as an arch ring. Opacity climbs toward the
                             apex on the same 0.25 / 0.55 / solid ramp as .kc-arch-mark, so
                             the two devices are visibly the same motif at two scales.
                             Index 3 is the keystone. --}}
                        <path d="M30.0,188.0 A140,140 0 0 1 43.0,131.0 L92.0,153.8 A86,86 0 0 0 84.0,188.8 Z" fill-opacity="0.38"/>
                        <path d="M44.7,127.5 A140,140 0 0 1 81.2,81.8 L115.4,123.5 A86,86 0 0 0 93.0,151.6 Z" fill-opacity="0.58"/>
                        <path d="M84.2,79.3 A140,140 0 0 1 136.9,54.0 L149.7,106.4 A86,86 0 0 0 117.3,122.0 Z" fill-opacity="0.78"/>
                        <path d="M140.8,53.1 A140,140 0 0 1 199.2,53.1 L188.0,105.9 A86,86 0 0 0 152.0,105.9 Z" fill="#D4AD56"/>
                        <path d="M203.1,54.0 A140,140 0 0 1 255.8,79.3 L222.7,122.0 A86,86 0 0 0 190.3,106.4 Z" fill-opacity="0.78"/>
                        <path d="M258.8,81.8 A140,140 0 0 1 295.3,127.5 L247.0,151.6 A86,86 0 0 0 224.6,123.5 Z" fill-opacity="0.58"/>
                        <path d="M297.0,131.0 A140,140 0 0 1 310.0,188.0 L256.0,188.8 A86,86 0 0 0 248.0,153.8 Z" fill-opacity="0.38"/>
                        {{-- Piers carrying the arch down to the springline --}}
                        <rect x="30" y="192" width="54" height="60" fill-opacity="0.28"/>
                        <rect x="256" y="192" width="54" height="60" fill-opacity="0.28"/>
                        <rect x="18" y="252" width="304" height="4" fill-opacity="0.55"/>
                    </g>
                </svg>

                {{-- Sentence-case caption for the figure. Deliberately not a
                     tracked-out uppercase gold line, which would read as the
                     kicker/eyebrow pattern even sitting below the graphic. --}}
                <p class="mt-5 text-center text-sm text-white/75 max-w-[260px]">
                    The keystone is the one piece the rest of the arch depends on.
                </p>
            </div>
        </div>
    </section>

    {{-- ── What we offer — two products, compared on the terms that actually
         differ, rather than two prose blocks that say the same thing twice. ── --}}
    <section class="kc-section-light">
        <div class="max-w-5xl mx-auto">
            <h2 class="kc-display-2">Two loans. One standard.</h2>
            <p class="mt-4 text-kc-navy/80 max-w-xl leading-relaxed">
                Personal or business, both are affordability checked before an offer
                exists and costed in full before you sign.
            </p>

            <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="kc-card !p-8 h-full flex flex-col">
                    <h3 class="font-display text-xl font-semibold text-kc-navy">Standard Loan</h3>
                    <p class="text-sm text-kc-navy/80 mt-2 leading-relaxed">
                        Short term, settled on your next payday.
                    </p>
                    <dl class="mt-6">
                        <div class="kc-spec kc-spec-stack">
                            <dt>Instalments</dt>
                            <dd><span class="kc-figure font-semibold">1</span>, on your next salary date</dd>
                        </div>
                        <div class="kc-spec kc-spec-stack">
                            <dt>Cost</dt>
                            <dd>A single once-off charge, fixed at signing</dd>
                        </div>
                        <div class="kc-spec kc-spec-stack">
                            <dt>Assessment</dt>
                            <dd>Standard affordability check</dd>
                        </div>
                    </dl>
                </div>

                <div class="kc-card !p-8 h-full flex flex-col">
                    <h3 class="font-display text-xl font-semibold text-kc-navy">Extended Loan</h3>
                    <p class="text-sm text-kc-navy/80 mt-2 leading-relaxed">
                        Spread over several months when one payday is too tight.
                    </p>
                    <dl class="mt-6">
                        <div class="kc-spec kc-spec-stack">
                            <dt>Instalments</dt>
                            <dd>Up to <span class="kc-figure font-semibold">3</span>, each on your salary date</dd>
                        </div>
                        <div class="kc-spec kc-spec-stack">
                            <dt>Cost</dt>
                            <dd>Fixed per instalment, set before you sign</dd>
                        </div>
                        <div class="kc-spec kc-spec-stack">
                            <dt>Assessment</dt>
                            <dd>Enhanced affordability check, so the term genuinely fits your budget</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    {{-- ── The differentiator, stated positively ─────────────────────────────
         "No repayment surprises" is the whole pitch, so it gets the page's
         second navy band and is spelled out as the actual disclosure a
         borrower receives, not as an adjective. Contrast on this band is
         computed: kc-gold labels 6.75:1, white/80 values 11.31:1, white
         heading 17.28:1 — all on kc-navy. ── --}}
    <section class="kc-section-dark">
        <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="kc-arch-mark mb-7" aria-hidden="true"></div>
                <h2 class="kc-display-2 !text-white">Nothing you haven't already seen.</h2>
                <p class="mt-5 text-white/75 leading-relaxed">
                    A loan goes wrong when the cost shows up after the signature.
                    Every Keystone agreement puts the whole picture in front of you
                    first, in writing, with the numbers spelled out.
                </p>
            </div>

            <dl class="lg:col-span-7">
                <div class="kc-spec kc-spec-dark">
                    <dt>Amount advanced</dt>
                    <dd>Exactly what will land in your account.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Cost of credit</dt>
                    <dd>Interest, initiation fee and service fee, itemised line by line.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Total repayable</dt>
                    <dd>The one number you are agreeing to pay back.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Every due date</dt>
                    <dd>Each instalment set against your salary day and listed in full.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>If a payment is missed</dt>
                    <dd>The charge and the process, disclosed before you sign rather than after.</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ── How it works — four courses of the same arch, numbered. ── --}}
    <section class="kc-section-plain">
        <div class="max-w-5xl mx-auto">
            <h2 class="kc-display-2">From application to payout.</h2>

            <ol class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach([
                    ['Apply', 'A few personal details, your salary date, and a clear copy of your ID.'],
                    ['Assess', 'We check affordability against what you actually earn and already owe.'],
                    ['Offer', 'You get the full cost and every repayment date in writing, before committing.'],
                    ['Payout', 'Accept the agreement and the funds are released to your account.'],
                ] as $i => [$stepTitle, $stepBody])
                    <li>
                        <span class="kc-step-no">{{ $i + 1 }}</span>
                        <h3 class="font-display text-lg font-semibold text-kc-navy mt-4">{{ $stepTitle }}</h3>
                        <p class="text-sm text-kc-charcoal/70 mt-2 leading-relaxed">{{ $stepBody }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ── Closing CTA ── --}}
    <section class="kc-section-light">
        <div class="max-w-3xl mx-auto text-center">
            <div class="kc-arch-mark kc-arch-mark-ink mx-auto mb-7" aria-hidden="true"></div>
            <h2 class="kc-display-2">Find out what you qualify for.</h2>
            <p class="mt-4 text-kc-navy/80 max-w-lg mx-auto leading-relaxed">
                The application takes a few minutes. You will see the full cost of any
                offer before you are asked to accept it.
            </p>
            <a href="{{ route('register') }}" class="kc-btn-primary inline-flex mt-8">Apply for a Loan</a>
            <p class="mt-6 text-sm text-kc-charcoal/70">
                Already applied? <a href="{{ route('login') }}" class="kc-link">Sign in to your account</a>
            </p>
        </div>
    </section>

</x-marketing-layout>
