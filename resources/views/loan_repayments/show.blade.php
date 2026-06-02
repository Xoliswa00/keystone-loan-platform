<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Repayment Detail</span>
  </x-slot>
  <div class="kc-card max-w-xl">
    <table class="kc-table">
      <tbody>
        <tr><td class="text-kc-charcoal/50 text-xs w-32">Loan</td><td>#{{ str_pad($loanRepayment->loan_id ?? 0,6,'0',STR_PAD_LEFT) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Total</td><td class="font-semibold">R {{ number_format($loanRepayment->payment_amount,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Principal</td><td>R {{ number_format($loanRepayment->principal_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Interest</td><td>R {{ number_format($loanRepayment->interest_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Fees</td><td>R {{ number_format($loanRepayment->fee_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Date</td><td>{{ \Carbon\Carbon::parse($loanRepayment->payment_date)->format('d M Y') }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Method</td><td>{{ ucfirst(str_replace('_',' ',$loanRepayment->payment_method??'—')) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Status</td><td><span class="kc-badge {{ $loanRepayment->status==='paid'?'kc-badge-green':'kc-badge-red' }}">{{ ucfirst($loanRepayment->status) }}</span></td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">GL Ref</td><td class="font-mono text-xs">{{ $loanRepayment->gl_batch_reference??'—' }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Reference</td><td>{{ $loanRepayment->payment_reference??'—' }}</td></tr>
        @if($loanRepayment->notes)<tr><td class="text-kc-charcoal/50 text-xs">Notes</td><td class="text-xs">{{ $loanRepayment->notes }}</td></tr>@endif
      </tbody>
    </table>
    <a href="{{ route('loanrepayments.index') }}" class="kc-btn-ghost text-sm mt-4 inline-flex">Back</a>
  </div>
</x-app-layout>
