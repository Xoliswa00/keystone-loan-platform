@section('title', 'Privacy Policy — Keystone Capital Partners')
@section('description', 'How Keystone Capital Partners processes your personal information under POPIA.')

<x-marketing-layout>
  <section class="kc-section-light">
    <div class="max-w-3xl mx-auto px-6 py-16">
      <h1 class="kc-display-2 text-kc-navy mb-8">Privacy Policy</h1>

      <div class="kc-card">
        <div class="mb-5 flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-kc-gold/15 border border-kc-gold/30 flex items-center justify-center">
            <span class="font-display font-bold text-kc-gold text-sm">KC</span>
          </div>
          <div>
            <p class="font-semibold text-kc-navy">Keystone Capital Partners</p>
            <p class="text-xs text-kc-charcoal/60">Registered Credit Provider · Protection of Personal Information Act 4 of 2013</p>
          </div>
        </div>

        <div class="space-y-5 text-sm text-kc-charcoal/80">
          @foreach([
        ['1. What we collect', 'Name, ID number, contact details, physical address, ID document, salary/payment day, bank statements, and other financial information you submit as part of a credit application, together with information we receive from registered credit bureaux.'],
        ['2. Why we process it', 'To assess affordability under Section 81 of the National Credit Act, to administer your account and agreements, to communicate about your application or loan, to report your repayment behaviour to credit bureaux as required by law, and to meet our NCR/NCA/FICA regulatory obligations.'],
        ['3. Credit bureau checks', 'With your consent, we submit your details to registered credit bureaux to obtain a credit report, and we report your repayment history to those bureaux on an ongoing basis for the life of any credit agreement.'],
        ['4. Legal basis', 'Processing for credit assessment and account administration is necessary to perform the credit agreement you apply for, and is also required by the National Credit Act. Where processing is not otherwise required by law or contract, we rely on your consent.'],
        ['5. Your rights', 'You may access the personal information we hold about you, request that it be corrected, and object to or request the withdrawal of consent for processing that relies on consent. You can manage your consent from your account profile at any time. Withdrawing consent for data processing or credit bureau checks may prevent us from assessing or continuing a credit application.'],
        ['6. Retention', 'We retain your information for as long as your account is active and thereafter for the period required by the National Credit Act, Financial Intelligence Centre Act, and other applicable law.'],
        ['7. Sharing', 'We share information with registered credit bureaux, the National Credit Regulator where required, our banking and payment partners for the purpose of collecting repayments, and service providers who process data on our behalf under confidentiality obligations. We do not sell your personal information.'],
        ['8. Security', 'We apply reasonable technical and organisational safeguards to protect your information against loss, unauthorised access, and disclosure.'],
        ['9. Complaints', 'If you believe we have not handled your information correctly, you may lodge a complaint with the Information Regulator (South Africa).'],
        ['10. Contact', 'For any request relating to your personal information, contact us using the details on our Contact page.'],
          ] as [$heading, $content])
          <div class="border-b border-kc-silver-light pb-4 last:border-0">
            <h5 class="font-semibold text-kc-navy mb-1">{{ $heading }}</h5>
            <p>{{ $content }}</p>
          </div>
          @endforeach
        </div>

        <div class="mt-5 pt-4 border-t border-kc-silver-light text-xs text-kc-charcoal/60">
          NCR Registration: {{ \App\Models\Company::settings()?->ncr_number ?? 'Contact us for details' }} · <em>Capital. Partnership. Growth.</em>
        </div>
      </div>
    </div>
  </section>
</x-marketing-layout>
