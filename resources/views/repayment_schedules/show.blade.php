<x-app-layout>
  <x-slot name="header"><span class="kc-page-title">Schedule Detail</span></x-slot>
  <div class="kc-card max-w-xl">
    <table class="kc-table">
      <tbody>
        <tr><td class="text-kc-charcoal/50 text-xs w-32">Instalment #</td><td>{{ $repaymentSchedule->installment_number ?? '—' }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Due Date</td><td>{{ \Carbon\Carbon::parse($repaymentSchedule->due_date)->format('d M Y') }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Amount</td><td class="font-semibold">R {{ number_format($repaymentSchedule->emi_amount,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Principal</td><td>R {{ number_format($repaymentSchedule->principal_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Interest</td><td>R {{ number_format($repaymentSchedule->interest_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Fees</td><td>R {{ number_format($repaymentSchedule->fee_amount??0,2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50 text-xs">Status</td><td>
          @php $sc=match($repaymentSchedule->status??''){'paid'=>'kc-badge-green','payment_failed'=>'kc-badge-red','rejected'=>'kc-badge-silver',default=>'kc-badge-gold'}; @endphp
          <span class="kc-badge {{ $sc }}">{{ ucfirst(str_replace('_',' ',$repaymentSchedule->status)) }}</span>
        </td></tr>
        @if($repaymentSchedule->paid_at)<tr><td class="text-kc-charcoal/50 text-xs">Paid At</td><td>{{ \Carbon\Carbon::parse($repaymentSchedule->paid_at)->format('d M Y H:i') }}</td></tr>@endif
      </tbody>
    </table>
    <a href="{{ route('repaymentSchedules.index') }}" class="kc-btn-ghost text-sm mt-4 inline-flex">Back</a>
  </div>
</x-app-layout>
