@section('title', 'Personal & Business Loans — Keystone Capital Partners')
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
    <section class="kc-section-dark overflow-hidden py-24 sm:py-32 2xl:py-40">
        <div class="kc-container grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

            <div class="lg:col-span-7 animate-fadeIn">
                <div class="kc-arch-mark mb-8" aria-hidden="true"></div>

                <h1 class="kc-display-1 text-white [text-wrap:balance]">
                    Personal and business loans, decided in days.
                    {{-- The gold clause carries the actual differentiator (cost
                         disclosed up front), not a mood word — own line from lg so
                         it never starts mid-line; below lg it wraps naturally
                         rather than being forced by hard <br>s, which produced
                         very ragged lines at 390px. --}}
                    <span class="text-kc-gold lg:block">Every cost fixed before you sign.</span>
                </h1>

                <p class="mt-6 text-white/75 text-base sm:text-lg leading-relaxed max-w-xl">
                    Keystone assesses what you can actually afford, quotes the full
                    cost in writing, and sets every repayment against your salary
                    date so it never catches you out.
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
                {{-- No caption. If the graphic needs a sentence explaining what it
                     is, that's a reason to fix the graphic, not to add a line under
                     it — and the same arch reappears below as the "How it works"
                     step numbering, so it's read twice as a system rather than
                     once as an illustration. aria-label above carries the meaning
                     for anyone not seeing it. --}}
            </div>
        </div>
    </section>

    {{-- ── What we offer — two products, compared on the terms that actually
         differ, rather than two prose blocks that say the same thing twice. ── --}}
    <section class="kc-section-light">
        <div class="kc-container">
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
        <div class="kc-container grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="kc-arch-mark mb-7" aria-hidden="true"></div>
                <h2 class="kc-display-2 !text-white">Every number, before you sign.</h2>
                <p class="mt-5 text-white/75 leading-relaxed kc-prose">
                    A loan goes wrong when the cost shows up after the signature.
                    Every Keystone agreement puts the whole picture in front of you
                    first, in writing, with the numbers spelled out.
                </p>

                {{-- Worked example — the disclosure list opposite is categories;
                     this is what it actually looks like in Rand, so the page's
                     central claim isn't made entirely in prose. kc-navy-mid panel
                     on the kc-navy band gives the dark section real depth instead
                     of one flat navy value; white on kc-navy-mid is 15.96:1,
                     kc-gold-light on kc-navy-mid is 6.8:1. --}}
                <div class="mt-8 rounded-xl bg-kc-navy-mid p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-kc-gold-light">
                        Representative example
                    </p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-white/75">Amount advanced</dt>
                            <dd class="kc-figure text-white">R 2,000.00</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-white/75">Cost of credit (1 month)</dt>
                            <dd class="kc-figure text-white">R 412.00</dd>
                        </div>
                        <div class="flex justify-between border-t border-white/15 pt-2 font-semibold">
                            <dt class="text-white">Total repayable</dt>
                            <dd class="kc-figure text-kc-gold-light">R 2,412.00</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-white/75">Due</dt>
                            <dd class="kc-figure text-white">25th, on your payday</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-[11px] text-white/75 leading-relaxed">
                        Illustrative only — your actual cost depends on the amount, term and your
                        affordability assessment, and is confirmed in writing before you accept.
                    </p>
                </div>
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

    {{-- ── How it works — the arch's own stones, not a redrawn number. ──
         Same wedge silhouette and 0.38/0.58/0.78/solid opacity ramp as the
         hero voussoirs and .kc-arch-mark, so the motif carries through
         instead of being reinvented as a rounded gold square. Step 4
         (Payout) is the keystone: full solid gold. ── --}}
    <section class="kc-section-plain">
        <div class="kc-container">
            <div class="flex items-end gap-1.5" aria-hidden="true">
                @for ($n = 0; $n < 4; $n++)
                    <svg viewBox="0 0 44 44" class="w-9 h-9 sm:w-10 sm:h-10">
                        <path d="M5,7 L39,7 L30,37 L14,37 Z" fill="#C89B3C" fill-opacity="{{ [0.38, 0.58, 0.78, 1][$n] }}"/>
                    </svg>
                @endfor
            </div>
            <h2 class="kc-display-2 mt-5">From application to payout.</h2>

            <ol class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach([
                    ['Apply', 'A few personal details, your salary date, and a clear copy of your ID.'],
                    ['Assess', 'We check affordability against what you actually earn and already owe.'],
                    ['Offer', 'You get the full cost and every repayment date in writing, before committing.'],
                    ['Payout', 'Accept the agreement and the funds are released to your account.'],
                ] as $i => [$stepTitle, $stepBody])
                    <li>
                        <svg viewBox="0 0 44 44" class="w-11 h-11" role="img" aria-label="Step {{ $i + 1 }}">
                            <path d="M4,8 L40,8 L30,38 L14,38 Z" fill="#C89B3C" fill-opacity="{{ [0.38, 0.58, 0.78, 1][$i] }}"/>
                            <text x="22" y="27" text-anchor="middle" font-size="15" fill="#071B34" class="font-mono font-bold">{{ $i + 1 }}</text>
                        </svg>
                        <h3 class="font-display text-lg font-semibold text-kc-navy mt-4">{{ $stepTitle }}</h3>
                        <p class="text-sm text-kc-charcoal/70 mt-2 leading-relaxed">{{ $stepBody }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ── Closing CTA ── --}}
    <section class="kc-section-light">
        <div class="kc-container text-center">
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
