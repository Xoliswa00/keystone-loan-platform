@section('title', 'Contact Keystone Capital Partners')
@section('description', 'Reach Keystone Capital Partners on WhatsApp, by phone or by email, or send a message straight from this page.')

<x-marketing-layout>

    {{-- ── Hero ──────────────────────────────────────────────────────────────
         The contact details themselves live in the hero rather than a band
         below it: someone on this page wants a phone number, not a paragraph
         about how much we value hearing from them. ── --}}
    <section class="kc-section-dark py-24 sm:py-32 2xl:py-40">
        <div class="kc-container grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 animate-fadeIn">
            <div class="lg:col-span-5">
                <div class="kc-arch-mark mb-8" aria-hidden="true"></div>
                <h1 class="kc-display-1 text-white [text-wrap:balance]">Ask before you owe anything.</h1>
                <p class="mt-5 text-white/75 leading-relaxed">
                    Questions about an application, a repayment date or an existing
                    account. WhatsApp reaches us fastest.
                </p>
            </div>

            <dl class="lg:col-span-7">
                <div class="kc-spec kc-spec-dark">
                    <dt>WhatsApp &amp; phone</dt>
                    <dd>
                        <a href="tel:+27721853349" class="kc-figure kc-link-light text-base">+27&nbsp;72&nbsp;185&nbsp;3349</a>
                    </dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Email</dt>
                    <dd>
                        <a href="mailto:info@keystorecapital.co.za" class="kc-link-light break-all">info@keystorecapital.co.za</a>
                    </dd>
                </div>
                <div class="kc-spec kc-spec-dark">
                    <dt>Office hours</dt>
                    <dd>
                        <span class="kc-figure">Mon&ndash;Fri 08:00&ndash;17:00</span><br>
                        <span class="kc-figure">Sat 09:00&ndash;13:00</span> &middot; Closed Sundays
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ── WhatsApp + query form ── --}}
    <section class="kc-section-light">
        <div class="kc-container grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-5">
                <div class="kc-card !p-8 h-full flex flex-col">
                    {{-- WhatsApp glyph inlined rather than pulled from an external icon
                         CDN at runtime: one less third-party request on a regulated
                         site, and it can no longer render blank if the CDN is down. --}}
                    <svg class="w-9 h-9 text-kc-gold-muted" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <h2 class="font-display text-xl font-semibold text-kc-navy mt-4">Message us on WhatsApp</h2>
                    <p class="text-sm text-kc-charcoal/70 mt-2 leading-relaxed">
                        The quickest route for application updates and account support
                        during office hours.
                    </p>
                    <a href="https://wa.me/27721853349" target="_blank" rel="noopener"
                       class="kc-btn-primary mt-6 self-start">
                        {{-- currentColor keeps the glyph the same colour as the label;
                             it was hard-coded navy against white text before. --}}
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Chat on WhatsApp
                    </a>
                    {{-- mt-auto: the grid row stretches this card to match the taller
                         form card, and without it the extra height was left as dead
                         space under the button instead of being taken up here. --}}
                    <p class="text-xs text-kc-charcoal/70 mt-auto pt-6 border-t border-kc-silver-light">
                        Never send your ID document or banking details over an unsolicited
                        message. Keystone will not ask you for them that way.
                    </p>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="kc-card !p-0 overflow-hidden">
                    <div class="bg-kc-navy px-6 py-3">
                        {{-- kc-gold on kc-navy = 6.75:1 --}}
                        <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.2em]">Send a message</p>
                    </div>
                    <div class="p-6 sm:p-8">
                        @if(session('success'))
                            <div class="kc-alert-success mb-6" role="status">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="kc-alert-error mb-6" role="alert">{{ session('error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="contact-name" class="kc-label">Name</label>
                                <input id="contact-name" type="text" name="name" value="{{ old('name') }}" required maxlength="150"
                                       autocomplete="name"
                                       @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror
                                       class="kc-input @error('name') border-red-400 @enderror">
                                @error('name')<p id="contact-name-error" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="contact-email" class="kc-label">Email</label>
                                    <input id="contact-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                                           autocomplete="email"
                                           @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror
                                           class="kc-input @error('email') border-red-400 @enderror">
                                    @error('email')<p id="contact-email-error" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="contact-phone" class="kc-label">Phone <span class="normal-case tracking-normal font-normal">(optional)</span></label>
                                    <input id="contact-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20"
                                           autocomplete="tel" class="kc-input">
                                </div>
                            </div>

                            <div>
                                <label for="contact-message" class="kc-label">Message</label>
                                <textarea id="contact-message" name="message" rows="5" required maxlength="2000"
                                          @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror
                                          class="kc-input @error('message') border-red-400 @enderror">{{ old('message') }}</textarea>
                                @error('message')<p id="contact-message-error" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <button type="submit" class="kc-btn-primary w-full justify-center">Send Message</button>

                            <p class="text-xs text-kc-charcoal/70 pt-1">
                                We use these details to answer your query only. See our
                                <a href="{{ route('privacy') }}" class="kc-link">privacy policy</a>.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </section>

</x-marketing-layout>
