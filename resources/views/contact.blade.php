@section('title', 'Contact Keystone Capital Partners')
@section('description', 'Reach Keystone Capital Partners directly by WhatsApp, phone, or email.')

<x-marketing-layout>

    {{-- ── Intro ── --}}
    <section class="kc-section-dark">
        <div class="max-w-3xl mx-auto px-6">
            <h1 class="kc-display-2 text-white" data-aos="fade-up">Let's talk.</h1>
            <p class="mt-4 text-white/60 leading-relaxed max-w-md" data-aos="fade-up" data-aos-delay="80">
                Reach us directly. Our team responds quickly, especially on WhatsApp.
            </p>
        </div>
    </section>

    {{-- ── Channels — asymmetric: one featured channel + a compact list, not equal cards ── --}}
    <section class="kc-section-light">
        <div class="max-w-3xl mx-auto px-6 grid grid-cols-1 md:grid-cols-5 gap-10">

            <div class="md:col-span-3 kc-card !p-8" data-aos="fade-up">
                <img src="https://cdn.simpleicons.org/whatsapp/C89B3C" alt="" class="w-9 h-9 mb-4" aria-hidden="true">
                <h2 class="font-display text-xl font-semibold text-kc-navy">Message us on WhatsApp</h2>
                <p class="text-sm text-kc-navy/80 mt-2 leading-relaxed">
                    The quickest way to reach us for application updates or account support.
                </p>
                <a href="https://wa.me/27721853349" target="_blank" rel="noopener" class="kc-btn-primary mt-6">
                    <img src="https://cdn.simpleicons.org/whatsapp/071B34" alt="" class="w-4 h-4" aria-hidden="true">
                    Chat on WhatsApp
                </a>
            </div>

            <div class="md:col-span-2 divide-y divide-kc-silver-light" data-aos="fade-up" data-aos-delay="100">
                <div class="py-4 first:pt-0">
                    <p class="kc-label !mb-1">Email</p>
                    <a href="mailto:info@keystorecapital.co.za" class="text-sm text-kc-navy font-medium hover:text-kc-gold-muted transition-colors">
                        info@keystorecapital.co.za
                    </a>
                </div>
                <div class="py-4">
                    <p class="kc-label !mb-1">Phone</p>
                    <p class="text-sm text-kc-navy font-medium">+27 72 185 3349</p>
                </div>
                <div class="py-4">
                    <p class="kc-label !mb-1">Hours</p>
                    <p class="text-sm text-kc-navy/80">Mon to Fri, 08:00 to 17:00</p>
                    <p class="text-sm text-kc-navy/80">Sat, 09:00 to 13:00. Closed Sundays.</p>
                </div>
            </div>

        </div>
    </section>

    {{-- ── Query form — light section (only hero/footer stay navy); the form
         card gets a navy header bar, matching the accent pattern used for
         the pillar/value cards on Welcome and About. ── --}}
    <section class="kc-section-light">
        <div class="max-w-xl mx-auto px-6">
            <h2 class="kc-display-2 text-center" data-aos="fade-up">Send us a message.</h2>
            <p class="mt-3 text-kc-navy/80 text-center text-sm" data-aos="fade-up" data-aos-delay="60">
                Prefer email over WhatsApp? Leave your details and we'll get back to you.
            </p>

            <div class="kc-card !p-0 overflow-hidden mt-8" data-aos="fade-up" data-aos-delay="100">
                <div class="bg-kc-navy px-6 py-3">
                    <p class="text-kc-gold text-xs font-semibold uppercase tracking-[0.2em]">Query form</p>
                </div>
                <div class="p-8">
                    @if(session('success'))
                        <div class="kc-alert-success mb-6">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="kc-alert-error mb-6">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="contact-name" class="kc-label">Name</label>
                            <input id="contact-name" type="text" name="name" value="{{ old('name') }}" required maxlength="150"
                                   class="kc-input @error('name') border-red-400 @enderror">
                            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="contact-email" class="kc-label">Email</label>
                                <input id="contact-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                                       class="kc-input @error('email') border-red-400 @enderror">
                                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="contact-phone" class="kc-label">Phone (optional)</label>
                                <input id="contact-phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="20"
                                       class="kc-input">
                            </div>
                        </div>
                        <div>
                            <label for="contact-message" class="kc-label">Message</label>
                            <textarea id="contact-message" name="message" rows="4" required maxlength="2000"
                                      class="kc-input @error('message') border-red-400 @enderror">{{ old('message') }}</textarea>
                            @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="kc-btn-primary w-full justify-center">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

</x-marketing-layout>
