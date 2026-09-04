<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Approve Application</span>
  </x-slot>
  <div class="max-w-xl mx-auto">
    <div class="kc-card">
      <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
        <div><span class="text-kc-charcoal/60">Client</span><p class="font-semibold">{{ $application->user?->name }}</p></div>
        <div><span class="text-kc-charcoal/60">Amount</span><p class="font-semibold text-kc-navy">R {{ number_format($application->loan_amount, 2) }}</p></div>
        <div><span class="text-kc-charcoal/60">Type</span><p>{{ ucfirst($application->loan_type) }}</p></div>
        <div><span class="text-kc-charcoal/60">Term</span><p>{{ $application->loan_term_months ?? 1 }} month(s)</p></div>
        @if($application->loanfee)
        <div><span class="text-kc-charcoal/60">Total Due</span><p class="font-semibold">R {{ number_format($application->loanfee->total_due, 2) }}</p></div>
        @endif
      </div>
      <form method="POST" action="{{ route('loans.approve', $application->id) }}">
        @csrf
        <div class="mb-4">
          <label class="kc-label">Approval Comments (optional)</label>
          <textarea name="approval_comments" rows="3" class="kc-input" placeholder="Notes for the record..."></textarea>
        </div>
        <div class="flex gap-3">
          <a href="{{ route('Admin.show', $application->id) }}" class="kc-btn-ghost">Back</a>
          <button type="submit" class="kc-btn-primary flex-1 justify-center">Confirm Approval</button>
        </div>
      </form>
    </div>
  </div>
</x-app-layout>
