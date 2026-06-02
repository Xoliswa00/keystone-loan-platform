<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9.5pt;
    color: #1a1a1a;
    line-height: 1.5;
    background: #fff;
  }

  /* ── Layout ── */
  .page        { padding: 18mm 18mm 15mm; }
  .header      { border-bottom: 2px solid #071B34; padding-bottom: 10px; margin-bottom: 16px; }
  .footer      { border-top: 1px solid #C89B3C; padding-top: 8px; margin-top: 16px; font-size: 8pt; color: #555; }
  .section     { margin-bottom: 14px; }
  .section-title {
    font-size: 10pt; font-weight: bold; color: #071B34;
    border-left: 3px solid #C89B3C; padding-left: 7px;
    margin-bottom: 8px; margin-top: 14px;
  }

  /* ── Tables ── */
  table        { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .detail-table td { padding: 4px 6px; vertical-align: top; }
  .detail-table td:first-child { font-weight: 600; width: 42%; color: #333; }
  .detail-table tr:nth-child(even) td { background: #f9f9f7; }

  .fee-table th { background: #071B34; color: #fff; padding: 5px 7px; font-size: 9pt; text-align: left; }
  .fee-table td { padding: 4px 7px; border-bottom: 1px solid #e8e9ec; font-size: 9pt; }
  .fee-table tr.total td { font-weight: bold; background: #f5f5f2; border-top: 2px solid #C89B3C; }

  .schedule-table th { background: #071B34; color: #fff; padding: 4px 6px; font-size: 8.5pt; text-align: left; }
  .schedule-table td { padding: 3px 6px; font-size: 8.5pt; border-bottom: 1px solid #eee; }
  .schedule-table tr:nth-child(even) td { background: #fafafa; }

  /* ── Brand ── */
  .brand-name  { font-size: 16pt; font-weight: bold; color: #071B34; letter-spacing: 1px; }
  .brand-sub   { font-size: 9pt; color: #C89B3C; letter-spacing: 2px; text-transform: uppercase; }
  .doc-title   { font-size: 13pt; font-weight: bold; color: #071B34; text-align: center; margin: 12px 0 4px; }
  .doc-ref     { font-size: 8.5pt; color: #777; text-align: center; margin-bottom: 12px; }

  /* ── Callouts ── */
  .box-navy    { background: #071B34; color: #fff; padding: 10px 12px; border-radius: 3px; margin-bottom: 12px; }
  .box-gold    { border: 1px solid #C89B3C; background: #fffbf2; padding: 9px 12px; border-radius: 3px; margin-bottom: 12px; }
  .box-warning { border: 1px solid #d97706; background: #fffbeb; padding: 9px 12px; border-radius: 3px; margin-bottom: 12px; font-size: 9pt; }
  .highlight   { color: #C89B3C; font-weight: bold; }
  .amount      { font-size: 11pt; font-weight: bold; color: #071B34; }

  /* ── Signature ── */
  .sig-block   { border-top: 1px solid #333; margin-top: 24px; padding-top: 6px; min-width: 180px; display: inline-block; }
  .sig-row     { display: flex; gap: 40px; margin-top: 30px; }

  /* ── Utility ── */
  .text-center { text-align: center; }
  .text-right  { text-align: right; }
  .text-sm     { font-size: 8.5pt; }
  .text-muted  { color: #777; }
  .bold        { font-weight: bold; }
  .mt-8        { margin-top: 8px; }
  .mt-12       { margin-top: 12px; }
  .row-2col    { display: flex; gap: 20px; }
  .col         { flex: 1; }

  @page { margin: 0; }
</style>
</head>
<body>
<div class="page">

  {{-- ── Header ── --}}
  <div class="header">
    <table>
      <tr>
        <td style="width:60%">
          <div class="brand-name">KEYSTONE</div>
          <div class="brand-sub">Capital Partners</div>
          <div style="font-size:8pt;color:#555;margin-top:4px">
            {{ $companyAddress }}<br>
            NCR Registration: <strong>{{ $ncr_number }}</strong>
            @if($vat_number) &nbsp;|&nbsp; VAT: <strong>{{ $vat_number }}</strong>@endif
          </div>
        </td>
        <td style="text-align:right;vertical-align:top">
          <div style="font-size:8pt;color:#555">
            Tel: {{ $contact_phone }}<br>
            {{ $contact_email }}<br>
            Generated: {{ $generatedAt }}
          </div>
        </td>
      </tr>
    </table>
  </div>
