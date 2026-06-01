<x-guest-layout>
    <section class="max-w-5xl mx-auto py-16 px-6 text-white">
        {{-- Logo Section --}}
        <div class="flex justify-center mb-8" data-aos="zoom-in">
            <img src="{{ asset('assets/logo.png') }}" alt="Liger Holdings Logo" class="w-30 h-30 sm:w-28 sm:h-28 object-contain rounded-full shadow-lg bg-white/10 p-2 backdrop-blur-md">
        </div>

        {{-- Introduction --}}
        <h1 class="text-4xl font-bold mb-4 text-center">About Liger Holdings</h1>
        <p class="text-lg leading-relaxed mb-6 text-center">
            <strong>Liger Holdings</strong> is a proudly South African micro-finance and business support company 
            dedicated to empowering individuals, small businesses, and communities through accessible financial solutions.
            We believe that growth starts with opportunity — and our mission is to make those opportunities possible.
        </p>

        {{-- Mission Section --}}
        <h2 class="text-2xl font-semibold mt-10 mb-3">Our Mission</h2>
        <p class="leading-relaxed mb-6">
            To provide fair, transparent, and flexible micro-loan options that help people 
            build sustainable livelihoods, strengthen businesses, and achieve financial independence.  
            We aim to be more than just a lender — we’re a trusted partner in your financial journey.
        </p>

        {{-- Vision Section --}}
        <h2 class="text-2xl font-semibold mt-10 mb-3">Our Vision</h2>
        <p class="leading-relaxed mb-6">
            To become a leading name in ethical lending and financial empowerment — creating a culture of accountability, 
            growth, and shared success within our communities.
        </p>

        {{-- Core Values --}}
        <h2 class="text-2xl font-semibold mt-10 mb-3">Our Core Values</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong>Integrity:</strong> We conduct our business with honesty, transparency, and respect.</li>
            <li><strong>Empowerment:</strong> We help individuals and small enterprises take control of their financial future.</li>
            <li><strong>Community:</strong> We believe in collective growth — when one succeeds, we all move forward.</li>
            <li><strong>Innovation:</strong> We embrace technology and smart systems to simplify and enhance financial access.</li>
            <li><strong>Accountability:</strong> We uphold responsibility in every transaction and every partnership.</li>
        </ul>

        {{-- Closing Statement --}}
        <div class="mt-12 text-center">
            <p class="text-lg italic text-gray-200">
                “At Liger Holdings, we don’t just fund dreams — we help build them.”
            </p>
        </div>
    </section>
</x-guest-layout>
