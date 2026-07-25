<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Record Manual Payment</span>
    <p class="kc-page-subtitle">For cash or EFT payments received outside Nu-Pay</p>
  </x-slot>

  <div class="kc-card max-w-lg">
    <form method="POST" action="{{ route('loanrepayments.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="kc-label">Repayment Schedule</label>
        <select name="repayment_schedule_id" class="kc-select" required>
          <option value="">Select instalment...</option>
          @foreach($schedules as $s)
          <option value="{{ $s->id }}">
            #{{ str_pad($s->id, 6, '0', STR_PAD_LEFT) }} · Due {{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }} · R {{ number_format($s->emi_amount, 2) }}
          </option>
          @endforeach
        </select>
        @error('repayment_schedule_id')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="kc-label">Amount Received (R)</label>
        <input type="number" name="payment_amount" step="0.01" min="0.01" class="kc-input" required>
        @error('payment_amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="kc-label">Payment Date</label>
        <input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="kc-input" required>
      </div>
      <div>
        <label class="kc-label">Payment Method</label>
        <select name="payment_method" class="kc-select" required>
          <option value="bank_transfer">EFT / Bank Transfer</option>
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="mobile_payment">Mobile Payment</option>
        </select>
      </div>
      <div>
        <label class="kc-label">Reference</label>
        <input type="text" name="payment_reference" class="kc-input" placeholder="Payment reference or proof number">
      </div>
      <div>
        <label class="kc-label">Notes</label>
        <textarea name="notes" rows="2" class="kc-input"></textarea>
      </div>
      <div class="flex gap-3 pt-2">
        <a href="{{ route('repaymentSchedules.index') }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary flex-1 justify-center">Record Payment + Post to GL</button>
      </div>
    </form>
  </div>
</x-app-layout>
