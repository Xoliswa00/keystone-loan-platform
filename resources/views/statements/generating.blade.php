@section('title', 'Generating Statement — Keystone Capital Partners')

<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Statement</span>
    <p class="kc-page-subtitle">{{ $period_label }}</p>
  </x-slot>

  <div class="max-w-md mx-auto text-center py-12"
    x-data="statementPoller('{{ $poll_url }}', '{{ $download_url }}')"
    x-init="startPolling()">

    <div class="kc-card">
      {{-- Spinner --}}
      <div x-show="!ready" class="mb-6">
        <div class="w-16 h-16 rounded-full border-4 border-kc-silver-light border-t-kc-gold mx-auto animate-spin mb-4"></div>
        <h3 class="font-display text-xl font-semibold text-kc-navy">Generating your statement</h3>
        <p class="text-sm text-kc-charcoal/50 mt-2">
          This usually takes 10–30 seconds depending on your loan history.
          <br>You can stay on this page or come back in a moment.
        </p>
      </div>

      {{-- Ready --}}
      <div x-show="ready" class="mb-6">
        <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
        </div>
        <h3 class="font-display text-xl font-semibold text-kc-navy">Statement ready!</h3>
        <p class="text-sm text-kc-charcoal/50 mt-2">Your statement for {{ $period_label }} has been generated.</p>
      </div>

      {{-- Error --}}
      <div x-show="error" class="mb-6">
        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
        <h3 class="font-display text-xl font-semibold text-red-600">Generation failed</h3>
        <p class="text-sm text-kc-charcoal/50 mt-2" x-text="errorMessage"></p>
      </div>

      {{-- Actions --}}
      <div class="flex flex-col gap-3">
        <a :href="downloadUrl" x-show="ready"
          class="kc-btn-primary justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Download PDF
        </a>
        <a href="{{ route('dashboard') }}" class="kc-btn-ghost justify-center text-sm">Back to Dashboard</a>
      </div>
    </div>
  </div>
</x-app-layout>

<script>
function statementPoller(pollUrl, downloadUrl) {
  return {
    ready: false,
    error: false,
    errorMessage: '',
    downloadUrl: downloadUrl,
    attempts: 0,
    maxAttempts: 30, // 30 × 3s = 90s max

    startPolling() {
      const interval = setInterval(async () => {
        this.attempts++;

        if (this.attempts > this.maxAttempts) {
          clearInterval(interval);
          this.error = true;
          this.errorMessage = 'Statement generation timed out. Please contact support.';
          return;
        }

        try {
          const resp = await fetch(pollUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await resp.json();

          if (data.ready) {
            clearInterval(interval);
            this.ready = true;
            this.downloadUrl = data.download;
          }
        } catch (e) {
          // network error — keep polling
        }
      }, 3000); // poll every 3 seconds
    }
  };
}
</script>
