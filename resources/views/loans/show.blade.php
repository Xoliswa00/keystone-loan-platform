<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Loan Details</span>
    <p class="kc-page-subtitle">Application #{{ str_pad($loanApplication->id, 6, '0', STR_PAD_LEFT) }}</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

      {{-- Status + summary --}}
      @php
        $sc = match(strtolower($loanApplication->status ?? 'pending')) {
          'approved','disbursed','settled' => 'kc-badge-green',
          'rejected','written_off'         => 'kc-badge-red',
          'pending','under_review'         => 'kc-badge-gold',
          default                          => 'kc-badge-silver',
        };
      @endphp
      <div class="kc-card">
        <div class="flex items-center justify-between mb-4">
          <span class="kc-badge {{ $sc }} text-sm">{{ ucfirst($loanApplication->status) }}</span>
          @if($loanApplication->loanfee)
          <span class="text-xs text-kc-charcoal/50">Total due: <strong>R {{ number_format($loanApplication->loanfee->total_due, 2) }}</strong></span>
          @endif
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div><p class="text-xs text-kc-charcoal/50">Amount</p><p class="font-display text-lg font-bold text-kc-navy">R {{ number_format($loanApplication->loan_amount, 2) }}</p></div>
          <div><p class="text-xs text-kc-charcoal/50">Term</p><p class="font-semibold">{{ $loanApplication->loan_term_months ?? 1 }} month(s)</p></div>
          <div><p class="text-xs text-kc-charcoal/50">Applied</p><p class="font-semibold text-xs">{{ $loanApplication->created_at->format('d M Y') }}</p></div>
          <div><p class="text-xs text-kc-charcoal/50">Purpose</p><p class="font-semibold text-xs truncate">{{ $loanApplication->purpose ?? '—' }}</p></div>
        </div>
      </div>

      {{-- Repayment schedule --}}
      @if($loanApplication->repaymentSchedules->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Repayment Schedule</h5>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>#</th><th>Due Date</th><th>Instalment</th><th>Status</th><th>Paid</th></tr></thead>
            <tbody>
              @foreach($loanApplication->repaymentSchedules->sortBy('installment_number') as $s)
              @php $ssc = match($s->status??''){'paid'=>'kc-badge-green','payment_failed'=>'kc-badge-red','rejected'=>'kc-badge-silver',default=>'kc-badge-gold'}; @endphp
              <tr class="{{ $s->status==='payment_failed'?'bg-red-50':'' }}">
                <td data-label="#">{{ $s->installment_number ?? '—' }}</td>
                <td data-label="Due">{{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}</td>
                <td data-label="Amount" class="font-semibold">R {{ number_format($s->emi_amount, 2) }}</td>
                <td data-label="Status"><span class="kc-badge {{ $ssc }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td>
                <td data-label="Paid" class="text-xs text-kc-charcoal/50">{{ $s->paid_at ? \Carbon\Carbon::parse($s->paid_at)->format('d M Y') : '—' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Loan fee breakdown --}}
      @if($loanApplication->loanfee)
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Cost of Credit</h5>
        <table class="kc-table">
          <tbody>
            <tr><td class="text-kc-charcoal/50 text-xs">Principal</td><td class="font-semibold">R {{ number_format($loanApplication->loan_amount, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/50 text-xs">Initiation Fee (incl. VAT)</td><td>R {{ number_format($loanApplication->loanfee->initiation_fee, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/50 text-xs">Service Fee</td><td>R {{ number_format($loanApplication->loanfee->service_fee, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/50 text-xs">Interest</td><td>R {{ number_format($loanApplication->loanfee->interest_amount, 2) }}</td></tr>
            <tr class="border-t-2 border-kc-gold/30 font-bold"><td>Total Repayable</td><td class="text-kc-gold">R {{ number_format($loanApplication->loanfee->total_due, 2) }}</td></tr>
          </tbody>
        </table>
      </div>
      @endif

    </div>

    {{-- Sidebar --}}
    <div class="space-y-4">
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Actions</h5>
        <div class="space-y-2">
          <a href="{{ route('repaymentSchedules.index') }}" class="kc-btn-ghost w-full justify-center text-sm">
            View Full Schedule
          </a>
          <a href="{{ route('client.my-statement') }}" class="kc-btn-ghost w-full justify-center text-sm">
            Download Statement
          </a>

          {{-- Early settlement request --}}
          @if($loanApplication->loan && in_array($loanApplication->loan->status, ['disbursed']))
          <div class="pt-2 border-t border-kc-silver-light">
            <p class="text-xs text-kc-charcoal/50 mb-2">Want to pay off early?</p>
            <form method="POST" action="{{ route('admin.agreements.settlement-quote', $loanApplication->loan) }}">
              @csrf
              <button type="submit" class="kc-btn-ghost w-full justify-center text-xs text-kc-gold border-kc-gold/30">
                Request Settlement Quote (NCA s.125)
              </button>
            </form>
          </div>
          @endif

          {{-- Agreements --}}
          @php $agreements = \App\Models\NcaAgreement::where('loan_application_id', $loanApplication->id)->get(); @endphp
          @if($agreements->isNotEmpty())
          <div class="pt-2 border-t border-kc-silver-light">
            <p class="text-xs text-kc-charcoal/50 mb-2">NCA Documents</p>
            @foreach($agreements as $ag)
            <div class="flex items-center justify-between py-1">
              <span class="text-xs truncate">{{ $ag->getTypeLabel() }}</span>
              <a href="{{ route('agreements.download', $ag) }}" class="text-xs text-kc-gold hover:underline ml-2 flex-shrink-0">PDF</a>
            </div>
            @endforeach
          </div>
          @endif
        </div>
      </div>

      @if($loanApplication->loan)
      <div class="kc-card">
        <h5 class="font-semibold text-kc-navy mb-3">Loan Account</h5>
        <table class="w-full text-xs">
          <tr class="border-b border-kc-silver-light/60"><td class="py-1.5 text-kc-charcoal/50">Remaining</td><td class="font-bold text-kc-navy">R {{ number_format($loanApplication->loan->remaining_balance ?? 0, 2) }}</td></tr>
          <tr class="border-b border-kc-silver-light/60"><td class="py-1.5 text-kc-charcoal/50">Disbursed</td><td>{{ $loanApplication->loan->disbursed_date ? \Carbon\Carbon::parse($loanApplication->loan->disbursed_date)->format('d M Y') : '—' }}</td></tr>
          <tr><td class="py-1.5 text-kc-charcoal/50">Next payment</td><td class="{{ $loanApplication->loan->next_payment_date && \Carbon\Carbon::parse($loanApplication->loan->next_payment_date)->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $loanApplication->loan->next_payment_date ? \Carbon\Carbon::parse($loanApplication->loan->next_payment_date)->format('d M Y') : '—' }}</td></tr>
        </table>
      </div>
      @endif
    </div>
  </div>
</x-app-layout>
