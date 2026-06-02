<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Edit Payment Record</span></x-slot>
  <div class="kc-card max-w-lg">
    <form method="POST" action="{{ route('loanrepayments.update', $loanRepayment) }}" class="space-y-4">
      @csrf @method('PUT')
      <div><label class="kc-label">Reference</label>
        <input type="text" name="payment_reference" value="{{ $loanRepayment->payment_reference }}" class="kc-input"></div>
      <div><label class="kc-label">Notes</label>
        <textarea name="notes" rows="3" class="kc-input">{{ $loanRepayment->notes }}</textarea></div>
      <p class="text-xs text-kc-charcoal/50">Only reference and notes can be edited. GL entries are immutable.</p>
      <div class="flex gap-3"><a href="{{ route('loanrepayments.index') }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary">Save</button></div>
    </form>
  </div>
</x-app-layout>
