<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Repayment Schedule</span></x-slot>
  <div class="kc-card max-w-lg">
    <p class="text-sm text-kc-charcoal/70 mb-4">Repayment schedules are generated automatically when a loan application is approved.</p>
    <a href="{{ route('repaymentSchedules.index') }}" class="kc-btn-ghost">Back</a>
  </div>
</x-app-layout>
