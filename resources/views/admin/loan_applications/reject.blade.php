<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Reject Application</span>
  </x-slot>
  <div class="max-w-xl mx-auto">
    <div class="kc-card">
      <div class="kc-alert-warning mb-4 text-xs">Client will be notified. NCR waiting period: {{ config("lending.rejection_cooldown_days", 30) }} days before reapplying.</div>
      <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
        <div><span class="text-kc-charcoal/50">Client</span><p class="font-semibold">{{ $application->user?->name }}</p></div>
        <div><span class="text-kc-charcoal/50">Amount</span><p class="font-semibold">R {{ number_format($application->loan_amount, 2) }}</p></div>
      </div>
      <form method="POST" action="{{ route('loans.reject', $application->id) }}">
        @csrf
        <div class="mb-4">
          <label class="kc-label">Rejection Reason <span class="text-red-500">*</span></label>
          <select name="rejection_reason" class="kc-select" required>
            <option value="">Select reason...</option>
            <option>Insufficient income to service the loan</option>
            <option>Existing loan not yet settled</option>
            <option>Incomplete or unverifiable documentation</option>
            <option>Credit record indicates elevated risk</option>
            <option>Requested amount exceeds affordability limits</option>
            <option>Employment duration insufficient</option>
          </select>
          @error("rejection_reason")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3">
          <a href="{{ route("Admin.show", $application->id) }}" class="kc-btn-ghost">Back</a>
          <button type="submit" class="kc-btn-danger flex-1 justify-center" onclick="return confirm(&quot;Reject this application?&quot;)">Confirm Rejection</button>
        </div>
      </form>
    </div>
  </div>
</x-app-layout>
