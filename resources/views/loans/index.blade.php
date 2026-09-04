<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">My Loans</span>
    <p class="kc-page-subtitle">Your loan accounts and repayment status</p>
  </x-slot>

  <div class="flex justify-end mb-4">
    <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary">+ New Application</a>
  </div>

  {{-- Active loans --}}
  @forelse($loans as $loan)
  @php
    $lsc = match(strtolower($loan->status??'')) {'disbursed','approved'=>'kc-badge-green','settled'=>'kc-badge-silver','rejected','written_off'=>'kc-badge-red',default=>'kc-badge-gold'};
  @endphp
  <div class="kc-card mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h4 class="font-display font-semibold text-kc-navy">Loan #{{ str_pad($loan->id,6,'0',STR_PAD_LEFT) }}</h4>
          <span class="kc-badge {{ $lsc }}">{{ ucfirst($loan->status) }}</span>
        </div>
        <p class="text-xs text-kc-charcoal/70">{{ ucfirst($loan->loan_type) }} · Approved {{ $loan->approved_at ? \Carbon\Carbon::parse($loan->approved_at)->format('d M Y') : '—' }}</p>
      </div>
      <div class="text-right">
        <p class="font-display text-2xl font-bold text-kc-navy">R {{ number_format($loan->remaining_balance ?? 0, 2) }}</p>
        <p class="text-xs text-kc-charcoal/70">Remaining balance</p>
      </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
      <div><p class="text-xs text-kc-charcoal/70">Principal</p><p class="font-semibold">R {{ number_format($loan->loan_amount,2) }}</p></div>
      <div><p class="text-xs text-kc-charcoal/70">Total Due</p><p class="font-semibold">R {{ number_format($loan->total_amount_due ?? $loan->loan_amount,2) }}</p></div>
      <div><p class="text-xs text-kc-charcoal/70">Term</p><p class="font-semibold">{{ $loan->loan_term_months ?? 1 }} month(s)</p></div>
      <div>
        <p class="text-xs text-kc-charcoal/70">Next Payment</p>
        <p class="font-semibold {{ $loan->next_payment_date && \Carbon\Carbon::parse($loan->next_payment_date)->isPast() ? 'text-red-600' : '' }}">
          {{ $loan->next_payment_date ? \Carbon\Carbon::parse($loan->next_payment_date)->format('d M Y') : '—' }}
        </p>
      </div>
    </div>
  </div>
  @empty
  <div class="kc-card text-center py-12">
    <p class="text-kc-charcoal/70 mb-4">No loans yet.</p>
    <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary">Apply for a Loan</a>
  </div>
  @endforelse

  {{-- Applications --}}
  @if($loansapp->isNotEmpty())
  <h4 class="font-display font-semibold text-kc-navy mt-8 mb-4">Applications</h4>
  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Ref</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($loansapp as $app)
          @php $asc = match(strtolower($app->status??'')){'approved','disbursed'=>'kc-badge-green','rejected'=>'kc-badge-red','pending'=>'kc-badge-gold',default=>'kc-badge-silver'}; @endphp
          <tr>
            <td data-label="Ref" class="font-mono text-xs">#{{ str_pad($app->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Amount" class="font-semibold">R {{ number_format($app->loan_amount,2) }}</td>
            <td data-label="Type">{{ ucfirst($app->loan_type) }}</td>
            <td data-label="Status"><span class="kc-badge {{ $asc }}">{{ ucfirst($app->status) }}</span></td>
            <td data-label="Date" class="text-xs text-kc-charcoal/70">{{ $app->created_at->format('d M Y') }}</td>
            <td data-label="Action"><a href="{{ route('loanapplications.show', $app->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
</x-app-layout>
