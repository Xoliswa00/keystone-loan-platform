@include('agreements.partials.header')

  <div class="doc-title">CREDIT AGREEMENT</div>
  <div class="doc-ref">
    Ref: {{ $applicationRef }} &nbsp;|&nbsp;
    National Credit Act 34 of 2005 &mdash; Section 93 &nbsp;|&nbsp;
    {{ $ncaCreditType }}
  </div>

  <div class="box-navy">
    <strong style="font-size:10pt">THIS IS A LEGALLY BINDING AGREEMENT.</strong>
    Read it carefully before signing. If you do not understand anything, ask for an explanation
    or contact a debt counsellor before signing.
  </div>

  {{-- ── Parties ── --}}
  <div class="section-title">1. THE PARTIES</div>
  <div class="row-2col">
    <div class="col">
      <table class="detail-table">
        <tr><td colspan="2"><strong>CREDIT PROVIDER</strong></td></tr>
        <tr><td>Name</td><td>{{ $companyName }}</td></tr>
        <tr><td>Registration</td><td>{{ $company_reg }}</td></tr>
        <tr><td>NCR Number</td><td>{{ $ncr_number }}</td></tr>
        <tr><td>VAT Number</td><td>{{ $vat_number ?: '—' }}</td></tr>
        <tr><td>Address</td><td>{{ $companyAddress }}</td></tr>
        <tr><td>Phone</td><td>{{ $contact_phone }}</td></tr>
        <tr><td>Email</td><td>{{ $contact_email }}</td></tr>
      </table>
    </div>
    <div class="col">
      <table class="detail-table">
        <tr><td colspan="2"><strong>CONSUMER</strong></td></tr>
        <tr><td>Full Name</td><td>{{ $clientName }}</td></tr>
        <tr><td>SA ID Number</td><td>{{ $clientId }}</td></tr>
        <tr><td>Date of Birth</td><td>{{ $clientDob }}</td></tr>
        <tr><td>Address</td><td>{{ $clientAddress }}</td></tr>
        <tr><td>Mobile</td><td>{{ $clientPhone }}</td></tr>
        <tr><td>Email</td><td>{{ $clientEmail }}</td></tr>
        <tr><td>Customer Code</td><td>{{ $customerCode }}</td></tr>
      </table>
    </div>
  </div>

  {{-- ── Credit terms ── --}}
  <div class="section-title">2. CREDIT TERMS (NCA s.93)</div>
  <table class="detail-table">
    <tr><td>Agreement Reference</td><td class="bold">{{ $applicationRef }}</td></tr>
    <tr><td>Credit Type</td><td>{{ $ncaCreditType }}</td></tr>
    <tr><td>Loan Type</td><td>{{ $loanType }}</td></tr>
    <tr><td>Principal Debt</td><td class="amount">R {{ number_format($principal, 2) }}</td></tr>
    <tr><td>Interest Rate</td><td class="bold">{{ $monthlyRatePct }}% per month ({{ $annualRatePct }}% per annum)</td></tr>
    <tr><td>Annual Percentage Rate (APR)</td><td class="bold">{{ $annualRatePct }}%</td></tr>
    <tr><td>Credit Term</td><td>{{ $termMonths }} month{{ $termMonths > 1 ? 's' : '' }}</td></tr>
    <tr><td>Number of Instalments</td><td>{{ $termMonths }}</td></tr>
    <tr><td>Monthly Instalment</td><td class="amount highlight">R {{ number_format($instalmentAmount, 2) }}</td></tr>
    <tr><td>First Instalment Due</td><td>{{ $firstPaymentDate }}</td></tr>
    <tr><td>Final Instalment Due</td><td>{{ $lastPaymentDate }}</td></tr>
    <tr><td><strong>Total Amount Repayable</strong></td><td class="amount highlight">R {{ number_format($totalAmountPayable, 2) }}</td></tr>
  </table>

  {{-- ── Cost of credit ── --}}
  <div class="section-title">3. TOTAL COST OF CREDIT (NCA s.102)</div>
  <table class="fee-table">
    <thead>
      <tr>
        <th style="width:40%">Fee Component</th>
        <th>Excl. VAT</th>
        <th>VAT @ 15%</th>
        <th>Incl. VAT</th>
        <th>Basis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Initiation Fee <em class="text-muted">(once-off)</em></td>
        <td>R {{ number_format($initiationFeeExcl, 2) }}</td>
        <td>R {{ number_format($initiationFeeVat, 2) }}</td>
        <td><strong>R {{ number_format($initiationFeeIncl, 2) }}</strong></td>
        <td>Once-off</td>
      </tr>
      <tr>
        <td>Service Fee <em class="text-muted">(per month)</em></td>
        <td>R {{ number_format($serviceFeeExcl, 2) }}</td>
        <td>R {{ number_format($serviceFeeVat, 2) }}</td>
        <td><strong>R {{ number_format($serviceFeeIncl, 2) }}</strong></td>
        <td>&times; {{ $termMonths }} = R {{ number_format($totalServiceFeeExcl + $totalServiceFeeVat, 2) }}</td>
      </tr>
      <tr>
        <td>Interest <em class="text-muted">({{ $monthlyRatePct }}% p/m — VAT exempt)</em></td>
        <td>R {{ number_format($totalInterest, 2) }}</td>
        <td>&mdash;</td>
        <td><strong>R {{ number_format($totalInterest, 2) }}</strong></td>
        <td>R {{ number_format($monthlyInterest, 2) }} &times; {{ $termMonths }}</td>
      </tr>
      <tr class="total">
        <td colspan="3"><strong>TOTAL COST OF CREDIT</strong></td>
        <td class="highlight"><span class="amount">R {{ number_format($totalCostOfCredit, 2) }}</span></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  {{-- ── Repayment schedule ── --}}
  <div class="section-title">4. REPAYMENT SCHEDULE</div>
  <table class="schedule-table">
    <thead>
      <tr>
        <th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th>
        <th>Fees (incl. VAT)</th><th>Instalment</th><th>Balance After</th>
      </tr>
    </thead>
    <tbody>
      @php $runningBalance = $principal; @endphp
      @foreach($schedule as $row)
      @php $runningBalance = max(0, $runningBalance - $row->principal_amount); @endphp
      <tr>
        <td>{{ $row->installment_number }}</td>
        <td>{{ \Carbon\Carbon::parse($row->due_date)->format('d M Y') }}</td>
        <td>R {{ number_format($row->principal_amount, 2) }}</td>
        <td>R {{ number_format($row->interest_amount, 2) }}</td>
        <td>R {{ number_format($row->fee_amount, 2) }}</td>
        <td><strong>R {{ number_format($row->emi_amount, 2) }}</strong></td>
        <td>R {{ number_format($runningBalance, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- ── Debit order mandate ── --}}
  <div class="section-title">5. DEBIT ORDER MANDATE</div>
  <p class="text-sm">
    By signing this agreement, I authorise <strong>{{ $companyName }}</strong> (or its payment
    collection agent) to debit my bank account for the monthly instalment amount specified above
    on or before each due date. This mandate will remain in force until all amounts owed under
    this agreement have been paid in full or until cancelled by written notice.
  </p>
  <table class="detail-table mt-8">
    <tr><td>Bank Name</td><td>____________________________</td></tr>
    <tr><td>Account Holder</td><td>{{ $clientName }}</td></tr>
    <tr><td>Account Number</td><td>____________________________</td></tr>
    <tr><td>Branch Code</td><td>____________________________</td></tr>
    <tr><td>Account Type</td><td>☐ Cheque / Current &nbsp;&nbsp; ☐ Savings</td></tr>
    <tr><td>Debit Date</td><td>As per repayment schedule above</td></tr>
  </table>

  {{-- ── Consumer rights ── --}}
  <div class="section-title">6. YOUR RIGHTS AND OBLIGATIONS</div>
  <div class="box-gold text-sm">
    <strong>Right to Rescission (NCA s.121):</strong>
    You may cancel this agreement without penalty by <strong>{{ $coolingOffExpiry }}</strong>
    ({{ $coolingOffDays }} business days from today). Return any funds received to cancel.<br><br>

    <strong>Early Settlement (NCA s.125):</strong>
    You may settle this agreement at any time. You are entitled to a rebate on
    unearned interest and unearned fees proportionate to the remaining term.<br><br>

    <strong>Default:</strong> If you miss an instalment, a dishonour fee of R40.00 (incl. VAT) may
    apply. After 20 business days of non-payment, the credit provider may proceed with debt
    enforcement. Your default will be reported to credit bureaux.<br><br>

    <strong>Credit Bureau Reporting:</strong> Your repayment behaviour will be reported monthly to
    registered credit bureaux. Positive payment history improves your credit score.<br><br>

    <strong>Complaints:</strong> Contact us first. Unresolved complaints may be referred to the
    National Credit Regulator: <strong>0860 627 627</strong> or the National Consumer Tribunal.
  </div>

  {{-- ── Warranties ── --}}
  <div class="section-title">7. CONSUMER WARRANTIES</div>
  <p class="text-sm">By signing below, I confirm that:</p>
  <ol class="text-sm" style="margin:6px 0 0 18px;line-height:1.8">
    <li>I am a South African citizen or permanent resident aged 18 years or older.</li>
    <li>All information provided in my application is true, accurate, and complete.</li>
    <li>I have not been sequestrated or declared insolvent without rehabilitation.</li>
    <li>I can afford the repayments as demonstrated in the affordability assessment.</li>
    <li>I consent to credit bureau checks and the reporting of this agreement.</li>
    <li>I have received and read the Pre-Agreement Statement prior to signing this agreement.</li>
    <li>I consent to the processing of my personal information under POPIA for purposes
        related to this credit agreement.</li>
  </ol>

  {{-- ── Signatures ── --}}
  <div class="section-title">8. SIGNATURES</div>
  <div class="row-2col" style="margin-top:20px">
    <div class="col">
      <div class="sig-block">Consumer Signature</div>
      <div class="text-sm mt-8"><strong>{{ $clientName }}</strong></div>
      <div class="text-sm text-muted">ID: {{ $clientId }}</div>
      <div class="text-sm mt-8">Date: ____________________</div>
      <div class="text-sm mt-8">Place: ____________________</div>
    </div>
    <div class="col">
      <div class="sig-block">For: {{ $companyName }}</div>
      <div class="text-sm mt-8"><strong>Authorised Representative</strong></div>
      <div class="text-sm text-muted">NCR Reg: {{ $ncr_number }}</div>
      <div class="text-sm mt-8">Date: {{ $today }}</div>
    </div>
  </div>

  <div class="row-2col" style="margin-top:24px">
    <div class="col">
      <div class="sig-block">Witness 1 Signature</div>
      <div class="text-sm mt-8">Name: ____________________</div>
      <div class="text-sm mt-8">ID: ____________________</div>
    </div>
    <div class="col">
      <div class="sig-block">Witness 2 Signature</div>
      <div class="text-sm mt-8">Name: ____________________</div>
      <div class="text-sm mt-8">ID: ____________________</div>
    </div>
  </div>

@include('agreements.partials.footer')
