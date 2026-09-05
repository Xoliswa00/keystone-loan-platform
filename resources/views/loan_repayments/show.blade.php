<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Repayment Detail</span>
  </x-slot>
  <div class="kc-card max-w-xl">
    <table class="kc-table">
      <tbody>
        <tr><td class="text-kc-charcoal/70 text-xs w-32">Loan</td><td>#{{ str_pad($loanRepayment->loan_id ?? 0,6,'0',STR_PAD_LEFT) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Total</td><td class="font-semibold">R {{ number_format($loanRepayment->payment_amount,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Principal</td><td>R {{ number_format($loanRepayment->principal_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Interest</td><td>R {{ number_format($loanRepayment->interest_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Fees</td><td>R {{ number_format($loanRepayment->fee_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Date</td><td>{{ \Carbon\Carbon::parse($loanRepayment->payment_date)->format('d M Y') }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Method</td><td>{{ ucfirst(str_replace('_',' ',$loanRepayment->payment_method??'—')) }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Status</td><td>
          @php $rsc = ['paid'=>'kc-badge-green','reversed'=>'kc-badge-red','pending_review'=>'kc-badge-gold','rejected'=>'kc-badge-silver'][$loanRepayment->status] ?? 'kc-badge-red'; @endphp
          <span class="kc-badge {{ $rsc }}">{{ ucfirst(str_replace('_',' ',$loanRepayment->status)) }}</span>
        </td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">GL Ref</td><td class="font-mono text-xs">{{ $loanRepayment->gl_batch_reference??'—' }}</td></tr>
        <tr><td class="text-kc-charcoal/70 text-xs">Reference</td><td>{{ $loanRepayment->payment_reference??'—' }}</td></tr>
        @if($loanRepayment->notes)<tr><td class="text-kc-charcoal/70 text-xs">Notes</td><td class="text-xs">{{ $loanRepayment->notes }}</td></tr>@endif
        @if($loanRepayment->reverses_repayment_id)
        <tr><td class="text-kc-charcoal/70 text-xs">Reverses</td><td class="text-xs">
          <a href="{{ route('loanrepayments.show', $loanRepayment->reverses_repayment_id) }}" class="text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Payment #{{ $loanRepayment->reverses_repayment_id }}</a>
        </td></tr>
        @endif
        @if($loanRepayment->reversal)
        <tr><td class="text-kc-charcoal/70 text-xs">Reversed by</td><td class="text-xs">
          <a href="{{ route('loanrepayments.show', $loanRepayment->reversal->id) }}" class="text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Reversal #{{ $loanRepayment->reversal->id }}</a>
        </td></tr>
        @endif
      </tbody>
    </table>

    @if($loanRepayment->status === 'paid' && ! $loanRepayment->reversal && Auth::user()->hasRole('loan_officer', 'it_admin'))
    <div class="mt-4 pt-4 border-t border-kc-silver-light" x-data="{open:false}">
      <button type="button" @click="open=!open" class="kc-btn-ghost text-xs text-red-600 border-red-200">Reverse Payment</button>
      <div x-show="open" x-transition class="mt-2">
        <form method="POST" action="{{ route('admin.payments.reverse', $loanRepayment) }}"
          onsubmit="return confirm('Reverse this payment? This will reopen the instalment and restore the loan balance.')">
          @csrf
          <textarea name="reversal_reason" rows="2" required maxlength="500"
            class="kc-input w-full text-xs" placeholder="Reason (required)"></textarea>
          <button type="submit" class="kc-btn-danger text-xs mt-2">Confirm Reversal</button>
        </form>
      </div>
    </div>
    @endif

    <a href="{{ route('loanrepayments.index') }}" class="kc-btn-ghost text-sm mt-4 inline-flex">Back</a>
  </div>
</x-app-layout>
