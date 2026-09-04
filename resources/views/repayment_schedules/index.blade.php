{{-- Redirect to loan_repayments/index which has the full schedule --}}
<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Repayment Schedule</span>
  </x-slot>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>#</th><th>Due Date</th><th>Instalment</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Status</th><th>Paid At</th>
        </tr></thead>
        <tbody>
          @forelse($loanRepayments ?? [] as $s)
          @php
            $ssc = match($s->status??''){'paid'=>'kc-badge-green','payment_failed'=>'kc-badge-red','rejected'=>'kc-badge-silver',default=>'kc-badge-gold'};
            $isOverdue = $s->status === 'pending' && \Carbon\Carbon::parse($s->due_date)->isPast();
          @endphp
          <tr class="{{ $isOverdue ? 'bg-red-50' : '' }}">
            <td data-label="#">{{ $s->installment_number ?? '—' }}</td>
            <td data-label="Due Date" class="{{ $isOverdue ? 'text-red-600 font-semibold' : '' }}">
              {{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}
            </td>
            <td data-label="Instalment" class="font-semibold">R {{ number_format($s->emi_amount,2) }}</td>
            <td data-label="Principal">R {{ number_format($s->principal_amount??0,2) }}</td>
            <td data-label="Interest">R {{ number_format($s->interest_amount??0,2) }}</td>
            <td data-label="Fees">R {{ number_format($s->fee_amount??0,2) }}</td>
            <td data-label="Status"><span class="kc-badge {{ $ssc }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td>
            <td data-label="Paid" class="text-xs text-kc-charcoal/60">{{ $s->paid_at ? \Carbon\Carbon::parse($s->paid_at)->format('d M Y') : '—' }}</td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/60">No schedule yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
