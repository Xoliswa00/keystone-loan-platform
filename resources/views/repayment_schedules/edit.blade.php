<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Repayment Schedule</span></x-slot>
  <div class="kc-card max-w-lg">
    <p class="text-sm text-kc-charcoal/70 mb-4">Repayment schedules are generated automatically and are read-only. Use the Collections workflow to manage overdue accounts.</p>
    <a href="{{ route('repaymentSchedules.index') }}" class="kc-btn-ghost">Back to Schedule</a>
  </div>
</x-app-layout>
