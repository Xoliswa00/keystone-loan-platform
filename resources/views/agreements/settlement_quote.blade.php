@include('agreements.partials.header')

  <div class="doc-title">SETTLEMENT QUOTATION</div>
  <div class="doc-ref">
    Ref: {{ $applicationRef }} &nbsp;|&nbsp;
    National Credit Act 34 of 2005 &mdash; Section 125 &nbsp;|&nbsp;
    Valid until {{ $quoteExpires }}
  </div>

  <div class="box-gold">
    This quotation is valid for <strong>5 business days</strong> from {{ $quoteDate }}.
    To settle, pay <span class="amount highlight">R {{ number_format($settlementAmt, 2) }}</span>
    by {{ $quoteExpires }} using your reference <strong>{{ $applicationRef }}</strong>.
  </div>

  <div class="section-title">SETTLEMENT BREAKDOWN (NCA s.125)</div>
  <table class="detail-table">
    <tr><td>Outstanding Balance</td><td class="amount">R {{ number_format($outstanding, 2) }}</td></tr>
    <tr><td>Less: Unearned Interest Rebate</td><td style="color:green">- R {{ number_format($interestRebate, 2) }}</td></tr>
    <tr><td>Less: Unearned Fee Rebate</td><td style="color:green">- R {{ number_format($feeRebate, 2) }}</td></tr>
    <tr>
      <td><strong>SETTLEMENT AMOUNT DUE</strong></td>
      <td><span class="amount highlight">R {{ number_format($settlementAmt, 2) }}</span></td>
    </tr>
  </table>

  <div class="section-title">CONSUMER DETAILS</div>
  <table class="detail-table">
    <tr><td>Name</td><td>{{ $clientName }}</td></tr>
    <tr><td>ID Number</td><td>{{ $clientId }}</td></tr>
    <tr><td>Customer Code</td><td>{{ $customerCode }}</td></tr>
  </table>

  <div class="section-title">PAYMENT INSTRUCTIONS</div>
  <p class="text-sm">
    Please use reference <strong>{{ $applicationRef }}</strong> when making payment.
    Once payment is confirmed, your loan account will be closed and a settlement letter issued.
    Contact us at <strong>{{ $contact_phone }}</strong> to confirm receipt.
  </p>

  <div class="box-warning text-sm" style="margin-top:14px">
    This quotation expires on <strong>{{ $quoteExpires }}</strong>. If payment is not received by
    this date, a new settlement quotation will be required as the outstanding balance may differ.
  </div>

  <div style="margin-top:30px;text-align:center;font-size:8.5pt;color:#555">
    Issued: {{ $generatedAt }} &nbsp;|&nbsp; {{ $companyName }} &nbsp;|&nbsp; NCR: {{ $ncr_number }}
  </div>

@include('agreements.partials.footer')
