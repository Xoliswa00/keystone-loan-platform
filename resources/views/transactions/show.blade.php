<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Transaction Detail</span></x-slot>
  <div class="kc-card max-w-lg">
    <table class="kc-table">
      <tbody>
        <tr><td class="text-kc-charcoal/70 text-xs w-28">ID</td><td class="font-mono">#{{ $transaction->id }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Amount</td><td class="font-semibold">R {{ number_format($transaction->amount,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Type</td><td><span class="kc-badge {{ $transaction->type==='credit'?'kc-badge-green':'kc-badge-red' }}">{{ ucfirst($transaction->type) }}</span></td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Date</td><td>{{ $transaction->transaction_date }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Description</td><td>{{ $transaction->description ?? '—' }}</td></tr>
      </tbody>
    </table>
    <div class="flex gap-3 mt-4">
      <a href="{{ route('transactions.index') }}" class="kc-btn-ghost">Back</a>
      <a href="{{ route('transactions.edit', $transaction) }}" class="kc-btn-ghost">Edit</a>
    </div>
  </div>
</x-app-layout>
