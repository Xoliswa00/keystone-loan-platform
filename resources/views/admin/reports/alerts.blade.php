<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">System Alerts</span>
    <p class="kc-page-subtitle">Live alerts from overdue accounts and upcoming payments</p>
  </x-slot>

  <div class="space-y-3">
    @forelse($alerts as $alert)
    @php
      $bgClass = $alert->severity === 'critical' ? 'border-red-400 bg-red-50' : 'border-amber-400 bg-amber-50';
      $iconClass = $alert->severity === 'critical' ? 'text-red-500' : 'text-amber-500';
    @endphp
    <div class="flex items-start gap-3 p-4 rounded-xl border {{ $bgClass }}">
      <div class="w-8 h-8 rounded-full {{ $alert->severity === 'critical' ? 'bg-red-100' : 'bg-amber-100' }} flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 {{ $iconClass }}" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
      </div>
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-0.5">
          <span class="text-xs font-bold uppercase tracking-wider {{ $iconClass }}">{{ $alert->type }}</span>
          @if(isset($alert->dpd) && $alert->dpd > 0)
            <span class="kc-badge kc-badge-red text-[10px]">{{ $alert->dpd }} DPD</span>
          @endif
        </div>
        <p class="text-sm font-semibold text-kc-charcoal">{{ $alert->client }}</p>
        <p class="text-xs text-kc-charcoal/60">
          Loan #{{ str_pad($alert->loan_id, 6, '0', STR_PAD_LEFT) }} ·
          R {{ number_format($alert->amount, 2) }}
          @if(isset($alert->dpd) && $alert->dpd > 0) · {{ $alert->dpd }} days overdue @endif
        </p>
      </div>
      <a href="{{ route('admin.collections') }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted flex-shrink-0">Action →</a>
    </div>
    @empty
    <div class="kc-card text-center py-12">
      <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
      </div>
      <p class="font-semibold text-kc-charcoal/60">No active alerts</p>
      <p class="text-xs text-kc-charcoal/60 mt-1">All accounts are in good standing</p>
    </div>
    @endforelse
  </div>
</x-app-layout>
