<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Edit Transaction</span></x-slot>
  <div class="kc-card max-w-lg">
    <form action="{{ route('transactions.update', $transaction) }}" method="POST" class="space-y-4">
      @csrf @method('PUT')
      <div><label class="kc-label">Amount</label>
        <input type="number" name="amount" value="{{ $transaction->amount }}" step="0.01" class="kc-input" required></div>
      <div><label class="kc-label">Type</label>
        <select name="type" class="kc-select" required>
          <option value="credit" {{ $transaction->type=='credit'?'selected':'' }}>Credit</option>
          <option value="debit"  {{ $transaction->type=='debit' ?'selected':'' }}>Debit</option>
        </select></div>
      <div><label class="kc-label">Date</label>
        <input type="date" name="transaction_date" value="{{ $transaction->transaction_date }}" class="kc-input" required></div>
      <div><label class="kc-label">Description</label>
        <textarea name="description" rows="2" class="kc-input">{{ $transaction->description }}</textarea></div>
      <div class="flex gap-3"><a href="{{ route('transactions.index') }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary">Update</button></div>
    </form>
  </div>
</x-app-layout>
