<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Loan Application Mismatch</title>
</head>
<body>
  <h2>Loan Application #{{ $application->id }} flagged</h2>
  <p>User: {{ $application->user->name ?? 'N/A' }} (ID: {{ $application->user_id }})</p>
  <p>Loan Amount: R{{ number_format($application->loan_amount, 2) }}</p>

  <h3>Posted values</h3>
  <ul>
    <li>Interest Rate: {{ $details['posted']['interest_rate'] }}</li>
    <li>Interest Amount (derived): R{{ number_format($details['posted']['interest_rate'] * $application->loan_amount, 2) }}</li>
    <li>Initiation Fee: R{{ number_format($details['posted']['initiation_fee'], 2) }}</li>
    <li>Service Fee: R{{ number_format($details['posted']['service_fee'], 2) }}</li>
    <li>Total Repayment: R{{ number_format($details['posted']['total_repayment'], 2) }}</li>
  </ul>

  <h3>Canonical values</h3>
  <ul>
    <li>Interest Rate: {{ $details['canonical']['interest_rate'] }}</li>
    <li>Interest: R{{ number_format($details['canonical']['interest'], 2) }}</li>
    <li>Initiation Fee: R{{ number_format($details['canonical']['initiation_fee'], 2) }}</li>
    <li>Service Fee: R{{ number_format($details['canonical']['service_fee'], 2) }}</li>
    <li>Total: R{{ number_format($details['canonical']['total'], 2) }}</li>
  </ul>

  <h3>Deltas</h3>
  <ul>
    <li>Total delta: R{{ number_format($details['deltas']['deltaTotal'], 2) }}</li>
    <li>Interest delta: R{{ number_format($details['deltas']['deltaInterest'], 2) }}</li>
    <li>Initiation delta: R{{ number_format($details['deltas']['deltaInitiation'], 2) }}</li>
    <li>Service delta: R{{ number_format($details['deltas']['deltaService'], 2) }}</li>
  </ul>

  <p>View the application in admin to review.</p>
</body>
</html>
