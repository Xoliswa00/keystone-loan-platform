@include('agreements.partials.header')

  <div class="doc-title">PRE-AGREEMENT STATEMENT</div>
  <div class="doc-ref">
    Ref: {{ $applicationRef }} &nbsp;|&nbsp;
    National Credit Act 34 of 2005 &mdash; Section 92 &nbsp;|&nbsp;
    {{ $ncaCreditType }}
  </div>

  {{-- ── Important notice ── --}}
  <div class="box-warning">
    <strong>IMPORTANT &mdash; PLEASE READ CAREFULLY:</strong>
    You have the right to receive this document before signing any credit agreement.
    You are not obliged to enter into this agreement. You have a <strong>{{ $coolingOffDays }}-business-day</strong>
    right of rescission after signing (NCA s.121).
  </div>

  {{-- ── Parties ── --}}
  <div class="section-title">1. PARTIES TO THE AGREEMENT</div>
  <div class="row-2col">
    <div class="col">
      <strong>CREDIT PROVIDER</strong>
      <table class="detail-table mt-8">
        <tr><td>Name</td><td>{{ $companyName }}</td></tr>
        <tr><td>NCR Registration</td><td>{{ $ncr_number }}</td></tr>
        <tr><td>Physical Address</td><td>{{ $companyAddress }}</td></tr>
        <tr><td>Contact</td><td>{{ $contact_phone }}</td></tr>
      </table>
    </div>
    <div class="col">
      <strong>CONSUMER (YOU)</strong>
      <table class="detail-table mt-8">
        <tr><td>Full Name</td><td>{{ $clientName }}</td></tr>
        <tr><td>SA ID Number</td><td>{{ $clientId }}</td></tr>
        <tr><td>Date of Birth</td><td>{{ $clientDob }}</td></tr>
        <tr><td>Address</td><td>{{ $clientAddress }}</td></tr>
        <tr><td>Mobile</td><td>{{ $clientPhone }}</td></tr>
      </table>
    </div>
  </div>

  {{-- ── Loan details ── --}}
  <div class="section-title">2. PRINCIPAL DEBT</div>
  <table class="detail-table">
    <tr><td>Loan Amount (Principal)</td><td class="amount">R {{ number_format($principal, 2) }}</td></tr>
    <tr><td>Credit Agreement Type</td><td>{{ $ncaCreditType }}</td></tr>
    <tr><td>Loan Type</td><td>{{ $loanType }}</td></tr>
    <tr><td>Number of Instalments</td><td>{{ $termMonths }}</td></tr>
    <tr><td>First Instalment Date</td><td>{{ $firstPaymentDate }}</td></tr>
    <tr><td>Final Instalment Date</td><td>{{ $lastPaymentDate }}</td></tr>
    <tr><td>Monthly Instalment</td><td class="amount highlight">R {{ number_format($instalmentAmount, 2) }}</td></tr>
  </table>

  {{-- ── Cost of credit (NCA s.102 prescribed disclosure) ── --}}
  <div class="section-title">3. TOTAL COST OF CREDIT (as prescribed by NCA s.102)</div>
  <table class="fee-table">
    <thead>
      <tr>
        <th>Fee Component</th>
        <th>Excl. VAT</th>
        <th>VAT (15%)</th>
        <th>Incl. VAT</th>
        <th>Frequency</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Initiation Fee <span class="text-sm text-muted">(NCA s.101(1)(a))</span></td>
        <td>R {{ number_format($initiationFeeExcl, 2) }}</td>
        <td>R {{ number_format($initiationFeeVat, 2) }}</td>
        <td><strong>R {{ number_format($initiationFeeIncl, 2) }}</strong></td>
        <td>Once-off</td>
      </tr>
      <tr>
        <td>Monthly Service Fee <span class="text-sm text-muted">(NCA s.101(1)(b))</span></td>
        <td>R {{ number_format($serviceFeeExcl, 2) }}</td>
        <td>R {{ number_format($serviceFeeVat, 2) }}</td>
        <td><strong>R {{ number_format($serviceFeeIncl, 2) }}</strong></td>
        <td>Per month &times; {{ $termMonths }}</td>
      </tr>
      <tr>
        <td>Interest <span class="text-sm text-muted">({{ $monthlyRatePct }}% p/m, NCA s.101(1)(d)) — VAT exempt</span></td>
        <td>R {{ number_format($totalInterest, 2) }}</td>
        <td>&mdash;</td>
        <td><strong>R {{ number_format($totalInterest, 2) }}</strong></td>
        <td>Per month &times; {{ $termMonths }}</td>
      </tr>
      <tr class="total">
        <td><strong>TOTAL COST OF CREDIT</strong></td>
        <td colspan="3" style="text-align:right"><span class="amount highlight">R {{ number_format($totalCostOfCredit, 2) }}</span></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <table class="detail-table">
    <tr><td>Annual Percentage Rate (APR)</td><td class="bold">{{ $annualRatePct }}% per annum</td></tr>
    <tr><td>Total Amount You Will Repay</td><td class="bold highlight">R {{ number_format($totalAmountPayable, 2) }}</td></tr>
  </table>

  {{-- ── Repayment schedule ── --}}
  <div class="section-title">4. REPAYMENT SCHEDULE</div>
  <table class="schedule-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Due Date</th>
        <th>Principal</th>
        <th>Interest</th>
        <th>Fees (incl. VAT)</th>
        <th>Instalment</th>
      </tr>
    </thead>
    <tbody>
      @foreach($schedule as $row)
      <tr>
        <td>{{ $row->installment_number }}</td>
        <td>{{ \Carbon\Carbon::parse($row->due_date)->format('d M Y') }}</td>
        <td>R {{ number_format($row->principal_amount, 2) }}</td>
        <td>R {{ number_format($row->interest_amount, 2) }}</td>
        <td>R {{ number_format($row->fee_amount, 2) }}</td>
        <td><strong>R {{ number_format($row->emi_amount, 2) }}</strong></td>
      </tr>
      @endforeach
      <tr style="background:#f5f5f2;font-weight:bold;border-top:2px solid #C89B3C">
        <td colspan="2">TOTAL</td>
        <td>R {{ number_format($schedule->sum('principal_amount'), 2) }}</td>
        <td>R {{ number_format($schedule->sum('interest_amount'), 2) }}</td>
        <td>R {{ number_format($schedule->sum('fee_amount'), 2) }}</td>
        <td>R {{ number_format($schedule->sum('emi_amount'), 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- ── Your rights (NCA) ── --}}
  <div class="section-title">5. YOUR RIGHTS UNDER THE NATIONAL CREDIT ACT</div>
  <div class="box-gold text-sm">
    <strong>Right to Rescission (NCA s.121):</strong> You may cancel this agreement without penalty
    within <strong>{{ $coolingOffDays }} business days</strong> of the date of the agreement
    (by {{ $coolingOffExpiry }}). You must return any goods received or repay any money received.<br><br>

    <strong>Right to Prepay (NCA s.125):</strong> You may settle this agreement early at any time.
    If you do, you are entitled to a <strong>rebate on unearned interest and fees</strong>.<br><br>

    <strong>Reckless Credit (NCA s.80):</strong> The credit provider has conducted an
    affordability assessment. If you believe this assessment was not conducted properly,
    you may apply to a court to have this agreement declared reckless credit.<br><br>

    <strong>Debt Review (NCA s.86):</strong> If you are over-indebted, you may apply to a
    debt counsellor for debt review. Contact the NCR on 0860 627 627 for a referral.<br><br>

    <strong>Dispute Resolution:</strong> You may refer a complaint to the National Credit
    Regulator (NCR) at <strong>0860 627 627</strong> or the National Consumer Tribunal.
  </div>

  {{-- ── Credit bureau ── --}}
  <div class="section-title">6. CREDIT BUREAU INFORMATION</div>
  <p class="text-sm">
    The credit provider will report information about this credit agreement and your payment
    behaviour to registered credit bureaux. This information may affect your credit record.
    You have the right to challenge incorrect information at a credit bureau.
    Contact the NCR at 0860 627 627 for a list of registered credit bureaux.
  </p>

  {{-- ── Acknowledgement ── --}}
  <div class="section-title">7. ACKNOWLEDGEMENT</div>
  <p class="text-sm mt-8">
    By accepting this agreement you confirm that:
  </p>
  <ul class="text-sm" style="margin:6px 0 0 18px">
    <li>You have read and understood this pre-agreement statement.</li>
    <li>You have received a copy for your records.</li>
    <li>The information you provided in your application is true and accurate.</li>
    <li>You can afford the repayments based on your declared income and expenses.</li>
  </ul>

  <div class="sig-row">
    <div>
      <div class="sig-block">Consumer Signature</div><br>
      <div class="text-sm text-muted">{{ $clientName }}</div>
      <div class="text-sm text-muted">Date: ____________________</div>
    </div>
    <div>
      <div class="sig-block">For: Keystone Capital Partners</div><br>
      <div class="text-sm text-muted">Authorised Signatory</div>
      <div class="text-sm text-muted">Date: {{ $today }}</div>
    </div>
  </div>

@include('agreements.partials.footer')
