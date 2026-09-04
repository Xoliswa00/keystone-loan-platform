@section('title', 'About Keystone Capital Partners')
@section('description', 'Keystone Capital Partners is an NCR-registered South African credit provider, founded by three partners on structured terms and full disclosure.')

<x-marketing-layout>

    {{-- ── Hero ── --}}
    <section class="kc-section-dark">
        <div class="max-w-3xl mx-auto animate-fadeIn">
            <div class="kc-arch-mark mb-8" aria-hidden="true"></div>

            <h1 class="kc-display-1 text-white [text-wrap:balance]">
                Built on strong <span class="text-kc-gold">foundations.</span>
            </h1>

            <p class="mt-6 text-white/75 text-base sm:text-lg leading-relaxed max-w-xl">
                Keystone Capital Partners is a South African credit provider. We lend
                our own capital, on structured terms, to people and businesses that
                intend to repay.
            </p>
        </div>
    </section>

    {{-- ── The name, and why it is the name ─────────────────────────────────
         The sunburst mark is placed as a companion to this passage at a size
         that reads, rather than dropped alone into whitespace. ── --}}
    <section class="kc-section-light">
        <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16 items-center">
            <div class="lg:col-span-7">
                <h2 class="kc-display-2">Three founders. One foundation.</h2>
                <p class="mt-5 text-kc-navy/80 leading-relaxed">
                    A keystone is the single wedge at the top of an arch. Every other
                    stone leans on it, and it is the piece that turns a pile of masonry
                    into a structure that carries weight.
                </p>
                <p class="mt-4 text-kc-navy/80 leading-relaxed">
                    That is the working arrangement between our three founding partners.
                    Each is individually accountable for their own part, and the whole
                    thing is built to hold under load. It is also how we write a loan
                    agreement: nothing in it depends on the borrower not reading it.
                </p>
            </div>

            <div class="lg:col-span-5 flex justify-center">
                {{-- Sunburst fan — the mark's gold ornament, the sanctioned graphic. --}}
                <svg width="300" height="172" viewBox="0 0 280 160" fill="none"
                     xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="w-full max-w-[300px] h-auto">
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(-36 140 150)"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(-18 140 150)"/>
                    <path d="M133,150 L147,150 L151,45 L129,45 Z" fill="#D4AD56"/>
                    <path d="M134,150 L146,150 L150,58 L130,58 Z" fill="#C89B3C" transform="rotate(18 140 150)"/>
                    <path d="M132,150 L148,150 L153,75 L127,75 Z" fill="#A07D2E" transform="rotate(36 140 150)"/>
                </svg>
            </div>
        </div>
    </section>

    {{-- ── Values ───────────────────────────────────────────────────────────
         These four live here only. They previously appeared verbatim on the
         welcome page as well, which made two pages read as one duplicated
         block; welcome now argues the product instead.
         The value words were kc-gold-muted on cream (3.52:1, fails AA for
         text this size) — they are kc-navy now (15.82:1), with the gold kept
         as a non-text rule beside them. ── --}}
    <section class="kc-section-plain">
        <div class="max-w-5xl mx-auto">
            <h2 class="kc-display-2">What holds us together.</h2>
            <p class="mt-5 text-kc-charcoal/70 leading-relaxed max-w-xl">
                We measure a decision against one question: does it strengthen the
                structure, or does it only make things faster. Structure wins.
            </p>

            <dl class="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-x-12">
                @foreach([
                    ['Capital', 'Our own funding, deployed with intent rather than volume targets.'],
                    ['Partnership', 'Decisions taken together, never in isolation.'],
                    ['Trust', 'Every agreement honoured in full, on the terms as written.'],
                    ['Growth', 'Measured in years, not quarters.'],
                ] as [$word, $line])
                    <div class="flex gap-4 border-t border-kc-silver-light py-5">
                        <span class="mt-2 h-8 w-0.5 shrink-0 rounded bg-kc-gold" aria-hidden="true"></span>
                        <div>
                            <dt class="font-display text-lg font-semibold text-kc-navy">{{ $word }}</dt>
                            <dd class="text-sm text-kc-charcoal/70 mt-1 leading-relaxed">{{ $line }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── Regulation ───────────────────────────────────────────────────────
         The NCR disclosure has to be visible. Given a full page about who we
         are, the honest place for it is a section that actually explains what
         the registration obliges us to do, rather than a decorative kicker
         above a headline. ── --}}
    <section class="kc-section-dark">
        <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14">
            <div class="lg:col-span-5">
                <div class="kc-arch-mark mb-7" aria-hidden="true"></div>
                <h2 class="kc-display-2 !text-white">How we are regulated.</h2>
                <p class="mt-5 text-white/75 leading-relaxed">
                    Keystone Lending (Pty) Ltd is a registered credit provider with the
                    National Credit Regulator. That registration is not a badge, it is a
                    set of obligations we are held to.
                </p>
            </div>

            <dl class="lg:col-span-7">
                <div class="kc-spec kc-spec-dark">
                    <dt>National Credit Act</dt>
                    <dd>Affordability must be assessed before credit is granted. No assessment, no offer.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Pre-agreement quote</dt>
                    <dd>You receive the full cost of the credit in writing, and time to consider it, before you sign anything.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>POPIA</dt>
                    <dd>Your personal information is processed for credit assessment and account administration only, with consent you can withdraw.</dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Complaints</dt>
                    <dd>Unresolved disputes can be taken to the National Credit Regulator or the Credit Ombud.</dd>
                </div>
            </dl>

            <div class="lg:col-span-12">
                <p class="text-sm text-white/75">
                    Read the full <a href="{{ route('terms') }}" class="kc-link-light">terms and conditions</a>
                    and our <a href="{{ route('privacy') }}" class="kc-link-light">privacy policy</a>.
                </p>
            </div>
        </div>
    </section>

    {{-- ── Closing ── --}}
    <section class="kc-section-light">
        <div class="max-w-3xl mx-auto text-center">
            <div class="kc-arch-mark kc-arch-mark-ink mx-auto mb-7" aria-hidden="true"></div>
            <h2 class="kc-display-2">Talk to us before you apply.</h2>
            <p class="mt-4 text-kc-navy/80 max-w-lg mx-auto leading-relaxed">
                If you would rather ask a question first, we would prefer that to a
                rushed application.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
                <a href="{{ route('register') }}" class="kc-btn-primary justify-center">Apply for a Loan</a>
                <a href="{{ route('contact') }}" class="kc-btn-ghost justify-center">Contact Keystone</a>
            </div>
        </div>
    </section>

</x-marketing-layout>
