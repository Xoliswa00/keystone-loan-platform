<x-guest-layout>
    <section class="min-h-screen bg-gray-900 text-gray-100">
        <div class="max-w-6xl mx-auto px-6 py-20">

            {{-- TOP: Logo + Heading --}}
            <div class="flex items-center gap-6 mb-10">
                <img src="{{ asset('assets/logo.png') }}" alt="Liger Holdings logo"
                     class="w-16 h-16 object-contain rounded-md bg-white/5 p-2">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight">Get in touch — Liger Holdings</h1>
                    <p class="text-sm text-gray-300 mt-1">Fast support for loan enquiries, disbursements and account help.</p>
                </div>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                {{-- LEFT: Contact channels --}}
                <div class="space-y-6">
                    <div class="bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-800/60">
                        <h2 class="text-lg font-semibold mb-2">Message us on WhatsApp</h2>
                        <p class="text-sm text-gray-400 mb-4">
                            Quickest way to get a response for application updates or support.
                        </p>
                        <a href="https://wa.me/+27721853349" target="_blank"
                           class="inline-flex items-center gap-3 bg-green-500 hover:bg-green-600 px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
                                <path d="M21 12.04C21 17.06 16.94 21 11.92 21c-1.84 0-3.57-.5-5.06-1.39L3 21l1.45-3.78A8.92 8.92 0 0 1 2 11.92C2 6.9 6.06 3 11.08 3c5.02 0 9.92 3.06 9.92 9.04z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M8.5 11.1c.4 1.1 1.6 1.9 2.9 1.6.6-.1 1.1-.4 1.5-.7.3-.3.8-.2 1.1.1.3.3.6.8.4 1.3-.4 1.1-1.6 2.8-4.7 3.2-2 .3-4.1-.6-5.1-2.1-.8-1.3-.7-3 .4-3.9.7-.6 1.6-.5 2.2.4z" fill="currentColor" opacity="0.9"></path>
                            </svg>
                            Chat on WhatsApp
                        </a>
                    </div>

                    <div class="bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-800/60">
                        <h2 class="text-lg font-semibold mb-2">Email & Phone</h2>
                        <p class="text-sm text-gray-400">Prefer email? Send detailed enquiries to:</p>
                        <p class="mt-3">
                            <a href="mailto:info@ligerholding.co.za" class="text-indigo-300 hover:underline">info@ligerholding.co.za</a>
                        </p>
                        <p class="text-sm text-gray-400 mt-3">Phone (office):</p>
                        <p class="mt-1 text-indigo-300">+27 72 185 3349</p>
                    </div>

                    <div class="bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-800/60">
                        <h2 class="text-lg font-semibold mb-2">Opening hours</h2>
                        <p class="text-sm text-gray-400">Mon — Fri: 08:00 — 17:00</p>
                        <p class="text-sm text-gray-400">Sat: 09:00 — 13:00 · Sun: Closed</p>
                        <p class="text-xs text-gray-500 mt-3">We reply on WhatsApp outside hours — response times may be slower.</p>
                    </div>
                </div>

                {{-- RIGHT: Contact form + map --}}
                <!--  <div class="space-y-6">
                    <div class="bg-white/5 p-6 rounded-xl border border-gray-800/60">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Send us a message</h3>

                        {{-- Flash / validation errors --}}
                        @if(session('success'))
                            <div class="mb-4 rounded-md bg-green-600/20 border border-green-600 p-3 text-sm text-green-200">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="mb-4 rounded-md bg-red-600/10 border border-red-600 p-3 text-sm text-red-300">
                                <ul class="list-disc pl-4">
                                    @foreach($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label for="name" class="block text-sm text-gray-300 mb-1">Full name</label>
                                <input id="name" name="name" type="text" required
                                       class="w-full rounded-md bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="email" class="block text-sm text-gray-300 mb-1">Email</label>
                                    <input id="email" name="email" type="email" required
                                           class="w-full rounded-md bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm text-gray-300 mb-1">Phone (optional)</label>
                                    <input id="phone" name="phone" type="text"
                                           class="w-full rounded-md bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label for="message" class="block text-sm text-gray-300 mb-1">Message</label>
                                <textarea id="message" name="message" rows="5" required
                                          class="w-full rounded-md bg-gray-900 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-md font-medium">
                                    Send message
                                </button>

                                <a href="https://wa.me/27721853349" target="_blank" class="text-sm text-indigo-300 hover:underline">
                                    Or start a chat on WhatsApp
                                </a>
                            </div>
                        </form>
                    </div>

                    {{-- Map / location placeholder --}}
                   <!--   <div class="bg-gray-800 p-6 rounded-xl border border-gray-800/60">
                        <h4 class="text-sm text-gray-300 mb-3">Visit Us</h4>
                        <div class="w-full h-44 bg-black/20 rounded-md flex items-center justify-center text-gray-400">
                            <!-- Replace with embedded map when ready 
                            Map placeholder — embed Google Maps or Leaflet here
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Head office: Liger Holdings (001) — First store (002).</p>
                    </div>-->
                </div>
            </div>

            {{-- FOOTER CTA --}}
            <div class="mt-12 text-center">
                <p class="text-sm text-gray-400">For urgent account changes, please call us or use WhatsApp — we respond fastest there.</p>
            </div>
        </div>
    </section>
</x-guest-layout>
