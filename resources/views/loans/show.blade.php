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
          'rejected','written_off','reversed' => 'kc-badge-red',
          'pending','under_review'         => 'kc-badge-gold',
          'affordability_review'           => 'kc-badge-navy',
          default                          => 'kc-badge-silver',
        };
      @endphp
      <div class="kc-card">
        <div class="flex items-center justify-between mb-4">
          <span class="kc-badge {{ $sc }} text-sm">{{ ucfirst(str_replace('_',' ',$loanApplication->status)) }}</span>
          @if($loanApplication->loanfee)
          <span class="text-xs text-kc-charcoal/60">Total due: <strong>R {{ number_format($loanApplication->loanfee->total_due, 2) }}</strong></span>
          @endif
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div><p class="text-xs text-kc-charcoal/60">Amount</p><p class="font-display text-lg font-bold text-kc-navy">R {{ number_format($loanApplication->loan_amount, 2) }}</p></div>
          <div><p class="text-xs text-kc-charcoal/60">Term</p><p class="font-semibold">{{ $loanApplication->loan_term_months ?? 1 }} month(s)</p></div>
          <div><p class="text-xs text-kc-charcoal/60">Applied</p><p class="font-semibold text-xs">{{ $loanApplication->created_at->format('d M Y') }}</p></div>
          <div><p class="text-xs text-kc-charcoal/60">Purpose</p><p class="font-semibold text-xs truncate">{{ $loanApplication->purpose ?? '—' }}</p></div>
        </div>
      </div>

      {{-- Counter-offer: staff proposed a different amount/term than originally requested --}}
      @if($pendingCounterOffer)
      <div class="kc-card border-2 border-kc-gold/40">
        <h5 class="font-display font-semibold text-kc-navy mb-2">A Revised Offer Is Ready</h5>
        <p class="text-sm text-kc-charcoal/70 mb-3">
          Your requested amount of R{{ number_format($loanApplication->loan_amount, 2) }} didn't pass our
          affordability check. Based on your income and expenses, we can offer you:
        </p>
        <div class="grid grid-cols-3 gap-3 text-center mb-4">
          <div><p class="text-xs text-kc-charcoal/60">Amount</p><p class="font-display text-lg font-bold text-kc-navy">R {{ number_format($pendingCounterOffer->amount, 2) }}</p></div>
          <div><p class="text-xs text-kc-charcoal/60">Term</p><p class="font-semibold">{{ $pendingCounterOffer->months }} month{{ $pendingCounterOffer->months > 1 ? 's' : '' }}</p></div>
          <div><p class="text-xs text-kc-charcoal/60">Instalment</p><p class="font-semibold">R {{ number_format($pendingCounterOffer->instalment, 2) }}/mo</p></div>
        </div>
        <p class="text-xs text-kc-charcoal/60 mb-3">Product: {{ $pendingCounterOffer->product->name }}</p>
        <div class="flex gap-2">
          <form method="POST" action="{{ route('counter-offers.accept', $loanApplication->id) }}" class="flex-1">
            @csrf
            <button type="submit" class="kc-btn-primary w-full justify-center">Accept Offer</button>
          </form>
          <form method="POST" action="{{ route('counter-offers.decline', $loanApplication->id) }}" class="flex-1"
            onsubmit="return confirm('Decline this offer? Your application will be marked unsuccessful.')">
            @csrf
            <button type="submit" class="kc-btn-ghost w-full justify-center">Decline</button>
          </form>
        </div>
      </div>
      @elseif($loanApplication->status === 'affordability_review')
      <div class="kc-card">
        <p class="text-sm text-kc-charcoal/70">
          The amount you requested exceeds what our affordability check currently supports.
          Our team is reviewing your application and may offer you an alternative amount — we'll notify you.
        </p>
      </div>
      @endif

      {{-- Repayment schedule --}}
      @if($loanApplication->repaymentSchedules->isNotEmpty())
      <div class="kc-card">
        <h5 class="font-display font-semibold text-kc-navy mb-3">Repayment Schedule</h5>
        <p class="text-xs text-kc-charcoal/60 mb-3">
          Paid by debit order automatically. If you paid another way (EFT/cash) and it hasn't reflected,
          upload proof of payment below — our team will verify it before it's marked paid.
        </p>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>#</th><th>Due Date</th><th>Instalment</th><th>Status</th><th>Paid</th><th>Proof of Payment</th></tr></thead>
            <tbody>
              @foreach($loanApplication->repaymentSchedules->sortBy('installment_number') as $s)
              @php
                $ssc = match($s->status??''){'paid'=>'kc-badge-green','payment_failed'=>'kc-badge-red','rejected'=>'kc-badge-silver',default=>'kc-badge-gold'};
                $claim = $s->latestManualClaim;
              @endphp
              <tr class="{{ $s->status==='payment_failed'?'bg-red-50':'' }}">
                <td data-label="#">{{ $s->installment_number ?? '—' }}</td>
                <td data-label="Due">{{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}</td>
                <td data-label="Amount" class="font-semibold">R {{ number_format($s->emi_amount, 2) }}</td>
                <td data-label="Status"><span class="kc-badge {{ $ssc }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span></td>
                <td data-label="Paid" class="text-xs text-kc-charcoal/60">{{ $s->paid_at ? \Carbon\Carbon::parse($s->paid_at)->format('d M Y') : '—' }}</td>
                <td data-label="Proof of Payment">
                  @if(in_array($s->status, ['pending', 'payment_failed'], true))
                    @if($claim && $claim->status === 'pending_review')
                      <span class="kc-badge kc-badge-gold text-[10px]">Under review</span>
                    @else
                      @if($claim && $claim->status === 'rejected')
                        <p class="text-[10px] text-red-600 mb-1">Declined: {{ $claim->rejection_reason }}</p>
                      @endif
                      <div x-data="{open:false}">
                        <button type="button" @click="open=!open" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">
                          {{ $claim && $claim->status === 'rejected' ? 'Resubmit Proof' : 'Upload Proof' }}
                        </button>
                        <form x-show="open" x-transition method="POST"
                          action="{{ route('repaymentSchedules.submit-proof', $s->id) }}"
                          enctype="multipart/form-data" class="mt-2 space-y-1.5 text-left">
                          @csrf
                          <input type="number" name="payment_amount" step="0.01" min="0.01" required
                            value="{{ $s->emi_amount }}" placeholder="Amount paid" class="kc-input text-xs w-full">
                          <input type="date" name="payment_date" required max="{{ now()->toDateString() }}"
                            class="kc-input text-xs w-full">
                          <input type="text" name="payment_reference" maxlength="100" placeholder="Reference (optional)"
                            class="kc-input text-xs w-full">
                          <input type="file" name="proof_of_payment" required accept=".pdf,.jpg,.jpeg,.png"
                            class="kc-input text-xs w-full">
                          <button type="submit" class="kc-btn-primary text-xs py-1 px-3 w-full justify-center">Submit</button>
                        </form>
                      </div>
                    @endif
                  @endif
                </td>
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
            <tr><td class="text-kc-charcoal/60 text-xs">Principal</td><td class="font-semibold">R {{ number_format($loanApplication->loan_amount, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/60 text-xs">Initiation Fee (incl. VAT)</td><td>R {{ number_format($loanApplication->loanfee->initiation_fee, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/60 text-xs">Service Fee</td><td>R {{ number_format($loanApplication->loanfee->service_fee, 2) }}</td></tr>
            <tr><td class="text-kc-charcoal/60 text-xs">Interest</td><td>R {{ number_format($loanApplication->loanfee->interest_amount, 2) }}</td></tr>
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
            <p class="text-xs text-kc-charcoal/60 mb-2">Want to pay off early?</p>
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
            <p class="text-xs text-kc-charcoal/60 mb-2">NCA Documents</p>
            @foreach($agreements as $ag)
            <div class="flex items-center justify-between py-1">
              <span class="text-xs truncate">{{ $ag->getTypeLabel() }}</span>
              <a href="{{ route('agreements.download', $ag) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted ml-2 flex-shrink-0">PDF</a>
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
          <tr class="border-b border-kc-silver-light/60"><td class="py-1.5 text-kc-charcoal/60">Remaining</td><td class="font-bold text-kc-navy">R {{ number_format($loanApplication->loan->remaining_balance ?? 0, 2) }}</td></tr>
          <tr class="border-b border-kc-silver-light/60"><td class="py-1.5 text-kc-charcoal/60">Disbursed</td><td>{{ $loanApplication->loan->disbursed_date ? \Carbon\Carbon::parse($loanApplication->loan->disbursed_date)->format('d M Y') : '—' }}</td></tr>
          <tr><td class="py-1.5 text-kc-charcoal/60">Next payment</td><td class="{{ $loanApplication->loan->next_payment_date && \Carbon\Carbon::parse($loanApplication->loan->next_payment_date)->isPast() ? 'text-red-600 font-semibold' : '' }}">{{ $loanApplication->loan->next_payment_date ? \Carbon\Carbon::parse($loanApplication->loan->next_payment_date)->format('d M Y') : '—' }}</td></tr>
        </table>
      </div>
      @endif
    </div>
  </div>
</x-app-layout>
