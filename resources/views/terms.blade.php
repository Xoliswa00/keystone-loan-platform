<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Terms & Conditions</span>
    <p class="kc-page-subtitle">Keystone Capital Partners — NCR Registered Credit Provider</p>
  </x-slot>

  <div class="kc-card max-w-3xl">
    <div class="mb-5 flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
        <span class="font-display font-bold text-kc-gold text-sm">KC</span>
      </div>
      <div>
        <p class="font-semibold text-kc-navy">Keystone Capital Partners</p>
        <p class="text-xs text-kc-charcoal/50">Registered Credit Provider · National Credit Act 34 of 2005</p>
      </div>
    </div>

    <div class="space-y-5 text-sm text-kc-charcoal/80">
      @foreach([
        ['1. Parties & Application', 'These terms govern the relationship between Keystone Capital Partners (Pty) Ltd ("the Credit Provider") and any person applying for or receiving credit. By submitting a loan application, you accept these terms unconditionally.'],
        ['2. Affordability Assessment (NCA s.81)', 'We are required under Section 81 of the National Credit Act to conduct an affordability assessment before approving credit. You must provide true, complete, and current information. Providing false information constitutes fraud and may result in immediate loan recall and legal action.'],
        ['3. Credit Fees and Costs (NCA s.102)', 'All applicable fees — initiation fee, monthly service fee, and interest — are disclosed in the Pre-Agreement Statement before signing. These comply with prescribed NCA maximums. VAT (15%) applies to initiation and service fees. Interest income is VAT-exempt.'],
        ['4. Right of Rescission (NCA s.121)', 'You may cancel this agreement without penalty within 5 business days of signing. All funds received must be returned to effect cancellation.'],
        ['5. Early Settlement (NCA s.125)', 'You may settle early at any time. Request a settlement quote showing the outstanding balance and applicable interest/fee rebate.'],
        ['6. Default and Collections', 'Failure to pay on the due date may result in a dishonour fee and adverse credit bureau listing. After 20 business days, we may issue a Section 129 notice and proceed with debt enforcement.'],
        ['7. Credit Bureau Reporting', 'Your repayment behaviour will be reported monthly to registered credit bureaux. Positive payment history improves your credit score.'],
        ['8. Privacy and POPIA', 'Your personal information is processed in accordance with POPIA. It is used solely for credit assessment and management. You have rights to access, correct, and request deletion of your data.'],
        ['9. Debt Review (NCA s.86)', 'If over-indebted, you may apply to a registered debt counsellor for debt review. Contact the NCR: 0860 627 627.'],
        ['10. Governing Law', 'These terms are governed by the laws of South Africa, including the NCA 34 of 2005, CPA 68 of 2008, and POPIA 4 of 2013.'],
      ] as [$heading, $content])
      <div class="border-b border-kc-silver-light pb-4 last:border-0">
        <h5 class="font-semibold text-kc-navy mb-1">{{ $heading }}</h5>
        <p>{{ $content }}</p>
      </div>
      @endforeach
    </div>

    <div class="mt-5 pt-4 border-t border-kc-silver-light text-xs text-kc-charcoal/40">
      NCR Registration: {{ \App\Models\Company::settings()?->ncr_number ?? 'Contact us for details' }} · <em>Capital. Partnership. Growth.</em>
    </div>
  </div>
</x-app-layout>
