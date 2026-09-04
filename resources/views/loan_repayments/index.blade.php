<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Repayment Schedule</span>
    <p class="kc-page-subtitle">Your payment history and upcoming instalments</p>
  </x-slot>

  {{-- Summary --}}
  @php
    $pending = $loanRepayments->where('status','pending');
    $overdue = $loanRepayments->where('status','payment_failed');
    $paid    = $loanRepayments->where('status','paid');
  @endphp
  <div class="grid grid-cols-3 gap-4 mb-6">
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Pending</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $pending->count() }}</p>
      <p class="text-xs text-kc-charcoal/70">R {{ number_format($pending->sum('emi_amount'),2) }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Failed</p>
      <p class="font-display text-2xl font-bold text-red-600 mt-1">{{ $overdue->count() }}</p>
      <p class="text-xs text-kc-charcoal/70">R {{ number_format($overdue->sum('emi_amount'),2) }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Paid</p>
      <p class="font-display text-2xl font-bold text-emerald-600 mt-1">{{ $paid->count() }}</p>
      <p class="text-xs text-kc-charcoal/70">R {{ number_format($paid->sum('emi_amount'),2) }}</p>
    </div>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>#</th><th>Due Date</th><th>Instalment</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Status</th><th>Paid At</th>
          @if(Auth::user()->hasRole('loan_officer', 'finance', 'it_admin'))<th></th>@endif
        </tr></thead>
        <tbody>
          @forelse($loanRepayments as $s)
          @php
            $ssc = match($s->status??'') {
              'paid'=>'kc-badge-green','payment_failed'=>'kc-badge-red',
              'rejected'=>'kc-badge-silver',default=>'kc-badge-gold'
            };
            $isOverdue = $s->status === 'pending' && \Carbon\Carbon::parse($s->due_date)->isPast();
          @endphp
          <tr class="{{ $isOverdue ? 'bg-red-50' : '' }}">
            <td data-label="#">{{ $s->installment_number ?? '—' }}</td>
            <td data-label="Due Date" class="{{ $isOverdue ? 'text-red-600 font-semibold' : '' }}">
              {{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}
              @if($isOverdue)<span class="kc-badge kc-badge-red text-[9px] ml-1">Overdue</span>@endif
            </td>
            <td data-label="Instalment" class="font-semibold">R {{ number_format($s->emi_amount,2) }}</td>
            <td data-label="Principal">R {{ number_format($s->principal_amount??0,2) }}</td>
            <td data-label="Interest">R {{ number_format($s->interest_amount??0,2) }}</td>
            <td data-label="Fees">R {{ number_format($s->fee_amount??0,2) }}</td>
            <td data-label="Status"><span class="kc-badge {{ $ssc }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td>
            <td data-label="Paid At" class="text-xs text-kc-charcoal/70">
              {{ $s->paid_at ? \Carbon\Carbon::parse($s->paid_at)->format('d M Y') : '—' }}
            </td>
            @if(Auth::user()->hasRole('loan_officer', 'finance', 'it_admin'))
            <td data-label="">
              @if($s->status === 'paid' && $s->paidRepayment)
              <a href="{{ route('loanrepayments.show', $s->paidRepayment->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View Payment →</a>
              @endif
            </td>
            @endif
          </tr>
          @empty
          <tr><td colspan="9" class="text-center py-8 text-kc-charcoal/70">No repayment schedule yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
