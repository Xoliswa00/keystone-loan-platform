<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Interest Records</span></x-slot>
  <div class="kc-card max-w-lg">
    <p class="text-xs text-kc-charcoal/60">Interest records are created automatically by the system.</p>
    <a href="{{ route('loaninterests.index') }}" class="kc-btn-ghost mt-3 inline-flex">Back</a>
  </div>
</x-app-layout>
