<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">VAT 201 — SARS Return Extract</span>
    <p class="kc-page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">From</label><input type="date" name="from_date" value="{{ $from }}" class="kc-input"></div>
    <div><label class="kc-label">To</label><input type="date" name="to_date" value="{{ $to }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Output VAT --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">OUTPUT VAT (Tax Collected)</h4>
      <table class="kc-table">
        <thead><tr><th>Description</th><th>Excl. VAT</th><th>VAT @ 15%</th><th>Incl. VAT</th></tr></thead>
        <tbody>
          <tr>
            <td>Initiation Fees</td>
            <td>R {{ number_format($outputVatOnFees->initiation_fee_excl ?? 0, 2) }}</td>
            <td class="text-kc-gold-muted font-semibold">R {{ number_format($outputVatOnFees->initiation_vat ?? 0, 2) }}</td>
            <td>R {{ number_format($outputVatOnFees->initiation_fee_incl ?? 0, 2) }}</td>
          </tr>
          <tr>
            <td>Monthly Service Fees</td>
            <td>R {{ number_format($outputVatOnFees->service_fee_excl ?? 0, 2) }}</td>
            <td class="text-kc-gold-muted font-semibold">R {{ number_format($outputVatOnFees->service_vat ?? 0, 2) }}</td>
            <td>R {{ number_format($outputVatOnFees->service_fee_incl ?? 0, 2) }}</td>
          </tr>
          <tr class="font-semibold border-t-2 border-kc-gold/30">
            <td>TOTAL OUTPUT VAT</td>
            <td></td>
            <td class="text-kc-gold font-bold text-base">R {{ number_format($totalOutputVat, 2) }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
      <p class="text-xs text-kc-charcoal/40 mt-3">Interest income of R{{ number_format($interestIncome, 2) }} is VAT-exempt under the financial services exemption (VAT Act s.2(1)(f)).</p>
    </div>

    {{-- Input VAT --}}
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">INPUT VAT (Tax Paid)</h4>
      <table class="kc-table">
        <thead><tr><th>Description</th><th>VAT Amount</th></tr></thead>
        <tbody>
          <tr>
            <td>Nu-Pay Collection Fees</td>
            <td class="font-semibold">R {{ number_format($totalInputVat, 2) }}</td>
          </tr>
          <tr class="font-semibold border-t-2 border-kc-gold/30">
            <td>TOTAL INPUT VAT</td>
            <td class="font-bold text-base">R {{ number_format($totalInputVat, 2) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-6 p-4 rounded-lg bg-kc-navy text-white">
        <p class="text-xs text-white/60 mb-1">NET VAT PAYABLE TO SARS</p>
        <p class="font-display text-2xl font-bold text-kc-gold">R {{ number_format($netVatPayable, 2) }}</p>
        <p class="text-xs text-white/60 mt-1">Output VAT R{{ number_format($totalOutputVat, 2) }} minus Input VAT R{{ number_format($totalInputVat, 2) }}</p>
      </div>
    </div>
  </div>

  <div class="kc-card mt-6">
    <p class="text-xs text-kc-charcoal/50">
      <strong>Note:</strong> This extract is for reference only. Verify against your accounting records before submitting VAT 201 to SARS.
      Submit via SARS eFiling at <strong>www.sarsefiling.co.za</strong>. VAT period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}.
    </p>
  </div>
</x-app-layout>
