@section('title', 'Apply for a Loan — Keystone Capital Partners')

<x-guest-layout>

    {{-- Heading --}}
    <div class="mb-6">
        <div class="kc-arch-mark kc-arch-mark-ink mb-6" aria-hidden="true"></div>
        <h1 class="font-display text-2xl font-semibold text-kc-navy">Open your account</h1>
        <p class="mt-1.5 text-sm text-kc-charcoal/70">
            Three short steps. Nothing is committed until you accept a written offer.
        </p>
    </div>

    {{-- Error summary --}}
    @if ($errors->any())
        <div class="kc-alert-error mb-5" role="alert">
            <p class="font-semibold mb-1">Please correct the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // A validation failure re-renders this page with the Alpine wizard
        // reset to step 1 by default — if the actual error is on step 2/3,
        // it sits hidden behind x-show and looks like the form did nothing.
        // Jump straight to the first step that actually has an error.
        $stepFields = [
            1 => ['name', 'ID_Number', 'email'],
            2 => ['phone', 'address', 'ID_copy', 'salary_payment_day'],
            3 => ['password', 'password_confirmation', 'terms', 'popia_consent'],
        ];
        $initialStep = 1;
        foreach ($stepFields as $stepNumber => $fields) {
            if ($errors->hasAny($fields)) {
                $initialStep = $stepNumber;
                break;
            }
        }
        $steps = [1 => 'About you', 2 => 'Contact & ID', 3 => 'Security'];
    @endphp

    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" novalidate
        class="space-y-5" x-data="{ step: {{ $initialStep }} }">
        @csrf

        {{-- ── Step indicator ───────────────────────────────────────────────
             Three courses of the arch. Active/complete marker is kc-navy on
             kc-gold (6.75:1); the earlier version put the step label in
             kc-gold-muted on cream, which measures 3.52:1 and fails AA. ── --}}
        <div class="mb-7">
            <p class="sr-only" aria-live="polite">
                Step <span x-text="step"></span> of {{ count($steps) }}
            </p>
            <ol class="flex items-start">
                @foreach($steps as $n => $label)
                    <li class="flex flex-col items-center gap-1.5 shrink-0 w-16">
                        <span :class="step >= {{ $n }} ? 'bg-kc-gold text-kc-navy' : 'bg-kc-silver-light text-kc-charcoal/70'"
                              class="kc-figure w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors">
                            {{ $n }}
                        </span>
                        <span :class="step >= {{ $n }} ? 'text-kc-navy' : 'text-kc-charcoal/70'"
                              class="text-[10px] font-semibold uppercase tracking-wide text-center leading-tight transition-colors">
                            {{ $label }}
                        </span>
                    </li>
                    @if(!$loop->last)
                        <li aria-hidden="true" class="flex-1 min-w-0 mt-3.5">
                            <div :class="step > {{ $n }} ? 'bg-kc-gold' : 'bg-kc-silver-light'"
                                 class="h-0.5 rounded transition-colors"></div>
                        </li>
                    @endif
                @endforeach
            </ol>
        </div>

        {{-- ── STEP 1 ──
             x-cloak on the inactive steps: x-show only takes effect once Alpine
             has booted, so without it all three steps render stacked and
             expanded for a beat on first paint. --}}
        <div x-show="step === 1" @if($initialStep !== 1) x-cloak @endif x-transition>
            <div class="space-y-4">
                <div>
                    <label for="reg-name" class="kc-label">Full name</label>
                    <input id="reg-name" type="text" name="name" value="{{ old('name') }}" required
                        autocomplete="name" placeholder="As it appears on your ID"
                        class="kc-input @error('name') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('name')"/>
                </div>

                <div>
                    <label for="reg-id" class="kc-label">South African ID number</label>
                    <input id="reg-id" type="text" name="ID_Number" value="{{ old('ID_Number') }}" required
                        inputmode="numeric" maxlength="13" pattern="[0-9]{13}" placeholder="13 digits"
                        class="kc-input kc-figure @error('ID_Number') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('ID_Number')"/>
                </div>

                <div>
                    <label for="reg-email" class="kc-label">Email address</label>
                    <input id="reg-email" type="email" name="email" value="{{ old('email') }}" required
                        autocomplete="email" placeholder="you@example.com"
                        class="kc-input @error('email') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('email')"/>
                </div>
            </div>

            <button type="button" @click="step = 2" class="kc-btn-primary w-full justify-center mt-6">
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- ── STEP 2 ── --}}
        <div x-show="step === 2" @if($initialStep !== 2) x-cloak @endif x-transition>
            <div class="space-y-4">
                <div>
                    <label for="reg-phone" class="kc-label">Mobile number</label>
                    <input id="reg-phone" type="tel" name="phone" value="{{ old('phone') }}" required
                        inputmode="tel" autocomplete="tel" placeholder="e.g. 0821234567"
                        class="kc-input kc-figure @error('phone') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('phone')"/>
                </div>

                <div>
                    <label for="reg-address" class="kc-label">Residential address</label>
                    <input id="reg-address" type="text" name="address" value="{{ old('address') }}" required
                        autocomplete="street-address" placeholder="Street address"
                        class="kc-input @error('address') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('address')"/>
                </div>

                <div>
                    <label for="reg-id-copy" class="kc-label">ID document</label>
                    <input id="reg-id-copy" type="file" name="ID_copy" required accept=".jpg,.jpeg,.png,.pdf"
                        aria-describedby="reg-id-copy-help"
                        class="kc-input py-2 cursor-pointer @error('ID_copy') border-red-400 @enderror">
                    {{-- kc-charcoal/70 on kc-white = 6.16:1. This helper text was
                         /60, which measures 4.44:1 and misses AA. --}}
                    <p id="reg-id-copy-help" class="mt-1.5 text-[11px] text-kc-charcoal/70">
                        A clear photo or scan. JPG, PNG or PDF, up to 5MB.
                    </p>
                    <x-input-error :messages="$errors->get('ID_copy')"/>
                </div>

                <div>
                    <label for="reg-salary-day" class="kc-label">Salary payment day</label>
                    <input id="reg-salary-day" type="number" name="salary_payment_day" value="{{ old('salary_payment_day') }}" required
                        min="1" max="31" inputmode="numeric" placeholder="e.g. 25"
                        aria-describedby="reg-salary-day-help"
                        class="kc-input kc-figure @error('salary_payment_day') border-red-400 @enderror">
                    <p id="reg-salary-day-help" class="mt-1.5 text-[11px] text-kc-charcoal/70">
                        The day of the month you are paid. Repayment dates are set to match it.
                    </p>
                    <x-input-error :messages="$errors->get('salary_payment_day')"/>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 1" class="kc-btn-ghost flex-1 justify-center">Back</button>
                <button type="button" @click="step = 3" class="kc-btn-primary flex-1 justify-center">Continue</button>
            </div>
        </div>

        {{-- ── STEP 3 ── --}}
        <div x-show="step === 3" @if($initialStep !== 3) x-cloak @endif x-transition>
            <div class="space-y-4" x-data="{ show: false, showConfirm: false }">
                <div>
                    <label for="reg-password" class="kc-label">Password</label>
                    <div class="relative">
                        <input id="reg-password" :type="show ? 'text' : 'password'" name="password" required
                            autocomplete="new-password" placeholder="Minimum 8 characters"
                            aria-describedby="reg-password-help"
                            class="kc-input pr-11 @error('password') border-red-400 @enderror">
                        <button type="button" @click="show = !show"
                            :aria-label="show ? 'Hide password' : 'Show password'"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded text-kc-charcoal/60 hover:text-kc-navy hover:bg-kc-silver-light transition-colors">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p id="reg-password-help" class="mt-1.5 text-[11px] text-kc-charcoal/70">
                        At least 8 characters, with letters and numbers.
                    </p>
                    <x-input-error :messages="$errors->get('password')"/>
                </div>

                <div>
                    <label for="reg-password-confirm" class="kc-label">Confirm password</label>
                    <div class="relative">
                        <input id="reg-password-confirm" :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required
                            autocomplete="new-password" placeholder="Re-enter your password"
                            class="kc-input pr-11">
                        <button type="button" @click="showConfirm = !showConfirm"
                            :aria-label="showConfirm ? 'Hide password' : 'Show password'"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded text-kc-charcoal/60 hover:text-kc-navy hover:bg-kc-silver-light transition-colors">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')"/>
                </div>
            </div>

            {{-- Terms acknowledgement --}}
            <div class="mt-5 p-3.5 rounded-lg border border-kc-silver-light bg-white">
                <label for="reg-terms" class="flex items-start gap-3 cursor-pointer">
                    <input id="reg-terms" type="checkbox" name="terms" required
                        class="mt-0.5 w-4 h-4 shrink-0 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/40">
                    <span class="text-xs text-kc-navy/80 leading-relaxed">
                        I agree to the
                        <a href="{{ route('terms') }}" class="kc-link" target="_blank" rel="noopener">terms and conditions</a>
                        and confirm the information I have given is true and accurate.
                    </span>
                </label>
            </div>

            {{-- POPIA / NCA consent — kept distinct from the Terms & Conditions
                 acknowledgement above so this consent is specific and informed,
                 not inferred from agreeing to the site's general terms. --}}
            <div class="mt-3 p-3.5 rounded-lg border border-kc-silver-light bg-white">
                <label for="reg-popia" class="flex items-start gap-3 cursor-pointer">
                    <input id="reg-popia" type="checkbox" name="popia_consent" required
                        class="mt-0.5 w-4 h-4 shrink-0 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/40">
                    <span class="text-xs text-kc-navy/80 leading-relaxed">
                        I consent to credit checks under the National Credit Act, and to my
                        personal information being processed for credit assessment and account
                        administration under our
                        <a href="{{ route('privacy') }}" class="kc-link" target="_blank" rel="noopener">privacy policy</a>
                        and POPIA. You can view or withdraw this consent at any time from your
                        account settings.
                    </span>
                </label>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 2" class="kc-btn-ghost flex-1 justify-center">Back</button>
                <button type="submit" class="kc-btn-primary flex-1 justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Submit application
                </button>
            </div>
        </div>
    </form>

    <p class="text-center text-sm text-kc-charcoal/70 mt-6 pt-6 border-t border-kc-silver-light">
        Already have an account? <a href="{{ route('login') }}" class="kc-link">Sign in</a>
    </p>

</x-guest-layout>
