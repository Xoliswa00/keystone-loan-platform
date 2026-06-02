@include('agreements.partials.header')

  <div class="doc-title">STATEMENT OF ACCOUNT</div>
  <div class="doc-ref">
    Ref: {{ $statementRef }} &nbsp;|&nbsp; As at {{ $statementDate }}
  </div>

  {{-- Client & summary ─────────────────────────────────────────────────────── --}}
  <div class="row-2col">
    <div class="col">
      <div class="section-title" style="margin-top:0">CLIENT DETAILS</div>
      <table class="detail-table">
        <tr><td>Name</td><td>{{ $user->name }}</td></tr>
        <tr><td>ID Number</td><td>{{ $user->ID_Number }}</td></tr>
        <tr><td>Customer Code</td><td>{{ $customer?->customer_code ?? '—' }}</td></tr>
        <tr><td>Phone</td><td>{{ $user->phone }}</td></tr>
        <tr><td>Email</td><td>{{ $user->email }}</td></tr>
      </table>
    </div>
    <div class="col">
      <div class="section-title" style="margin-top:0">ACCOUNT SUMMARY</div>
      <table class="detail-table">
        <tr><td>Total Outstanding</td><td class="amount highlight">R {{ number_format($totalOutstanding, 2) }}</td></tr>
        <tr><td>Active Loans</td><td>{{ $loans->whereIn('status', ['disbursed','payment_failed'])->count() }}</td></tr>
        <tr><td>Completed Loans</td><td>{{ $loans->where('status','settled')->count() }}</td></tr>
        @if($nextDue)
        <tr><td>Next Instalment</td><td class="bold">R {{ number_format($nextDue->emi_amount, 2) }}</td></tr>
        <tr><td>Next Due Date</td><td class="bold">{{ \Carbon\Carbon::parse($nextDue->due_date)->format('d F Y') }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  {{-- Loan accounts ──────────────────────────────────────────────────────────── --}}
  @foreach($loans as $loan)
  <div class="section-title">LOAN #{{ str_pad($loan->loan_application_id, 6, '0', STR_PAD_LEFT) }} — {{ strtoupper($loan->loan_type) }}</div>
  <table class="detail-table">
    <tr><td>Original Amount</td><td>R {{ number_format($loan->loan_amount, 2) }}</td></tr>
    <tr><td>Term</td><td>{{ $loan->loan_term_months ?? 1 }} month(s)</td></tr>
    <tr><td>Interest Rate</td><td>{{ round(($loan->interest_rate ?? 0.05) * 100, 2) }}% per month</td></tr>
    <tr><td>Status</td><td class="bold">{{ ucfirst($loan->status) }}</td></tr>
    <tr><td>Disbursed</td><td>{{ $loan->disbursed_date ? \Carbon\Carbon::parse($loan->disbursed_date)->format('d F Y') : '—' }}</td></tr>
    <tr><td>Remaining Balance</td><td class="amount @if($loan->remaining_balance > 0) highlight @endif">R {{ number_format($loan->remaining_balance ?? 0, 2) }}</td></tr>
  </table>

  {{-- Instalment schedule ─────────────────────────────────────────────────── --}}
  <table class="schedule-table" style="margin-bottom:14px">
    <thead>
      <tr>
        <th>#</th><th>Due Date</th><th>Amount Due</th><th>Status</th><th>Paid At</th>
      </tr>
    </thead>
    <tbody>
      @foreach($loan->repaymentSchedules->sortBy('installment_number') as $s)
      <tr>
        <td>{{ $s->installment_number }}</td>
        <td>{{ \Carbon\Carbon::parse($s->due_date)->format('d M Y') }}</td>
        <td>R {{ number_format($s->emi_amount, 2) }}</td>
        <td>
          @if($s->status === 'paid')
            <span style="color:green;font-weight:bold">PAID</span>
          @elseif($s->status === 'payment_failed')
            <span style="color:red;font-weight:bold">FAILED</span>
          @elseif($s->due_date < now()->toDateString())
            <span style="color:orange;font-weight:bold">OVERDUE</span>
          @else
            <span style="color:#555">PENDING</span>
          @endif
        </td>
        <td>{{ $s->paid_at ? \Carbon\Carbon::parse($s->paid_at)->format('d M Y') : '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endforeach

  {{-- Transaction history ──────────────────────────────────────────────────── --}}
  @if($repayments->isNotEmpty())
  <div class="section-title">PAYMENT HISTORY</div>
  <table class="schedule-table">
    <thead>
      <tr>
        <th>Date</th><th>Reference</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Total Paid</th><th>Method</th>
      </tr>
    </thead>
    <tbody>
      @foreach($repayments as $r)
      <tr>
        <td>{{ \Carbon\Carbon::parse($r->payment_date)->format('d M Y') }}</td>
        <td class="text-muted">{{ $r->payment_reference ?? $r->gl_batch_reference ?? '—' }}</td>
        <td>R {{ number_format($r->principal_amount, 2) }}</td>
        <td>R {{ number_format($r->interest_amount, 2) }}</td>
        <td>R {{ number_format($r->fee_amount, 2) }}</td>
        <td class="bold">R {{ number_format($r->payment_amount, 2) }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $r->payment_method ?? '—')) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="box-gold text-sm" style="margin-top:14px">
    This statement is generated under the National Credit Act 34 of 2005.
    For disputes or queries, contact us at {{ $company?->phone ?? '27721853349' }} or
    {{ $company?->email ?? '' }}.
    Credit provider: <strong>{{ $company?->name ?? 'Keystone Capital Partners' }}</strong> — NCR: {{ $ncr_number }}.
  </div>

@include('agreements.partials.footer')
