@section('title', 'Create Account — Keystone Capital Partners')

<x-guest-layout>

    {{-- Heading --}}
    <div class="mb-6">
        <h2 class="font-display text-2xl font-semibold text-kc-navy">Open an account</h2>
        <p class="mt-1 text-sm text-kc-charcoal/50">Complete the form below to apply for lending services</p>
    </div>

    {{-- Gold divider --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="h-px flex-1 bg-kc-gold/30"></div>
        <div class="w-1.5 h-1.5 rounded-full bg-kc-gold"></div>
        <div class="h-px w-6 bg-kc-gold/30"></div>
    </div>

    {{-- Error summary --}}
    @if ($errors->any())
        <div class="kc-alert-error mb-5">
            <p class="font-semibold mb-1">Please correct the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data"
        class="space-y-5" x-data="{ step: 1, totalSteps: 4 }">
        @csrf

        {{-- Step indicator --}}
        <div class="flex items-center gap-1 mb-6">
            @foreach(['Personal', 'Contact', 'Employment', 'Security'] as $i => $label)
            <div class="flex-1 flex flex-col items-center gap-1">
                <div :class="step >= {{ $i + 1 }} ? 'bg-kc-gold text-kc-navy' : 'bg-kc-silver-light text-kc-charcoal/40'"
                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors">
                    {{ $i + 1 }}
                </div>
                <span :class="step >= {{ $i + 1 }} ? 'text-kc-gold-muted' : 'text-kc-charcoal/30'"
                    class="text-[9px] font-semibold uppercase tracking-wider hidden sm:block transition-colors">
                    {{ $label }}
                </span>
            </div>
            @if($i < 3)
            <div :class="step > {{ $i + 1 }} ? 'bg-kc-gold/40' : 'bg-kc-silver-light'"
                class="h-px flex-1 transition-colors mb-3"></div>
            @endif
            @endforeach
        </div>

        {{-- ── STEP 1: Personal ── --}}
        <div x-show="step === 1" x-transition>
            <p class="text-xs font-semibold uppercase tracking-widest text-kc-gold mb-4">Personal Information</p>

            <div class="space-y-4">
                <div>
                    <label class="kc-label">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="As it appears on your ID"
                        class="kc-input @error('name') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('name')"/>
                </div>

                <div>
                    <label class="kc-label">South African ID Number</label>
                    <input type="text" name="ID_Number" value="{{ old('ID_Number') }}" required placeholder="13-digit ID number"
                        maxlength="13" pattern="[0-9]{13}"
                        class="kc-input font-mono @error('ID_Number') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('ID_Number')"/>
                </div>

                <div>
                    <label class="kc-label">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="your@email.com"
                        class="kc-input @error('email') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('email')"/>
                </div>
            </div>

            <button type="button" @click="step = 2" class="kc-btn-primary w-full justify-center mt-6">
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- ── STEP 2: Contact ── --}}
        <div x-show="step === 2" x-transition>
            <p class="text-xs font-semibold uppercase tracking-widest text-kc-gold mb-4">Contact Information</p>

            <div class="space-y-4">
                <div>
                    <label class="kc-label">Mobile Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="e.g. 0821234567"
                        class="kc-input @error('phone') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('phone')"/>
                </div>

                <div>
                    <label class="kc-label">Residential Address</label>
                    <input type="text" name="address" value="{{ old('address') }}" required placeholder="Street address"
                        class="kc-input @error('address') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('address')"/>
                </div>

                <div>
                    <label class="kc-label">ID Document</label>
                    <input type="file" name="ID_copy" required accept=".jpg,.jpeg,.png,.pdf"
                        class="kc-input py-2 cursor-pointer @error('ID_copy') border-red-400 @enderror">
                    <p class="mt-1 text-[11px] text-kc-charcoal/40">Clear copy — JPG, PNG or PDF, max 5MB</p>
                    <x-input-error :messages="$errors->get('ID_copy')"/>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 1" class="kc-btn-ghost flex-1 justify-center">Back</button>
                <button type="button" @click="step = 3" class="kc-btn-primary flex-1 justify-center">Continue</button>
            </div>
        </div>

        {{-- ── STEP 3: Employment ── --}}
        <div x-show="step === 3" x-transition>
            <p class="text-xs font-semibold uppercase tracking-widest text-kc-gold mb-4">Employment & Income</p>
            <p class="text-xs text-kc-charcoal/50 mb-4 -mt-2">
                Required for NCR affordability assessment under the National Credit Act.
            </p>

            <div class="space-y-4">
                <div>
                    <label class="kc-label">Employment Status</label>
                    <select name="employment_status" required
                        class="kc-select @error('employment_status') border-red-400 @enderror">
                        <option value="" disabled {{ old('employment_status') ? '' : 'selected' }}>Select status</option>
                        <option value="Full-time"     {{ old('employment_status') === 'Full-time' ? 'selected' : '' }}>Full-time employed</option>
                        <option value="Part-time"     {{ old('employment_status') === 'Part-time' ? 'selected' : '' }}>Part-time employed</option>
                        <option value="Self-employed" {{ old('employment_status') === 'Self-employed' ? 'selected' : '' }}>Self-employed</option>
                        <option value="Unemployed"    {{ old('employment_status') === 'Unemployed' ? 'selected' : '' }}>Unemployed</option>
                    </select>
                    <x-input-error :messages="$errors->get('employment_status')"/>
                </div>

                <div>
                    <label class="kc-label">Net Monthly Income (R)</label>
                    <input type="number" name="net_salary" value="{{ old('net_salary') }}" required
                        min="0" step="0.01" placeholder="e.g. 8500.00"
                        class="kc-input @error('net_salary') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('net_salary')"/>
                </div>

                <div>
                    <label class="kc-label">Salary Frequency</label>
                    <select name="salary_frequency" required
                        class="kc-select @error('salary_frequency') border-red-400 @enderror">
                        <option value="" disabled {{ old('salary_frequency') ? '' : 'selected' }}>Select frequency</option>
                        <option value="Monthly"    {{ old('salary_frequency') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="Bi-weekly"  {{ old('salary_frequency') === 'Bi-weekly' ? 'selected' : '' }}>Bi-weekly</option>
                    </select>
                    <x-input-error :messages="$errors->get('salary_frequency')"/>
                </div>

                <div>
                    <label class="kc-label">Salary Payment Day of Month</label>
                    <input type="number" name="salary_payment_day" value="{{ old('salary_payment_day') }}" required
                        min="1" max="31" placeholder="e.g. 25"
                        class="kc-input @error('salary_payment_day') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('salary_payment_day')"/>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 2" class="kc-btn-ghost flex-1 justify-center">Back</button>
                <button type="button" @click="step = 4" class="kc-btn-primary flex-1 justify-center">Continue</button>
            </div>
        </div>

        {{-- ── STEP 4: Security ── --}}
        <div x-show="step === 4" x-transition>
            <p class="text-xs font-semibold uppercase tracking-widest text-kc-gold mb-4">Account Security</p>

            <div class="space-y-4" x-data="{ show: false, showConfirm: false }">
                <div>
                    <label class="kc-label">Password</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" required
                            placeholder="Minimum 8 characters"
                            class="kc-input pr-10 @error('password') border-red-400 @enderror">
                        <button type="button" @click="show = !show"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-kc-silver hover:text-kc-charcoal/60 transition">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-kc-charcoal/40">At least 8 characters with letters and numbers</p>
                    <x-input-error :messages="$errors->get('password')"/>
                </div>

                <div>
                    <label class="kc-label">Confirm Password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required
                            placeholder="Re-enter your password"
                            class="kc-input pr-10">
                        <button type="button" @click="showConfirm = !showConfirm"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-kc-silver hover:text-kc-charcoal/60 transition">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')"/>
                </div>
            </div>

            {{-- Terms acknowledgement --}}
            <div class="mt-4 p-3 rounded-lg border border-kc-silver-light bg-kc-white">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="terms" required
                        class="mt-0.5 w-3.5 h-3.5 rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
                    <span class="text-xs text-kc-charcoal/60">
                        I agree to the
                        <a href="{{ route('terms') ?? '#' }}" class="text-kc-gold hover:underline" target="_blank">Terms & Conditions</a>
                        and consent to credit checks under the National Credit Act. I confirm all information provided is true and accurate.
                    </span>
                </label>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 3" class="kc-btn-ghost flex-1 justify-center">Back</button>
                <button type="submit" class="kc-btn-primary flex-1 justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Submit Application
                </button>
            </div>
        </div>

        {{-- Sign in link --}}
        <p class="text-center text-xs text-kc-charcoal/50 pt-2">
            Already have an account?
            <a href="{{ route('login') }}" class="text-kc-gold hover:text-kc-gold-muted transition font-medium">
                Sign in
            </a>
        </p>

    </form>

</x-guest-layout>
