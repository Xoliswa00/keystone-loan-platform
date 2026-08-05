@component('mail::message')

# {{ $agreement->getTypeLabel() }}

Your document is attached to this email as a PDF.

**Reference:** {{ $agreement->reference }}
&nbsp;|&nbsp; **Principal:** R{{ number_format($agreement->principal_amount, 2) }}
&nbsp;|&nbsp; **Term:** {{ $agreement->term_months }} month{{ $agreement->term_months == 1 ? '' : 's' }}

@if($agreement->document_type === 'pre_agreement_statement')
This is your pre-agreement statement — please review it before your loan agreement is finalised. It sets out the full cost of credit, so you know exactly what you're agreeing to before you sign anything.
@elseif($agreement->document_type === 'loan_agreement')
This is your signed credit agreement. Keep it for your records.
@endif

@component('mail::button', ['url' => config('app.url')])
Open Dashboard
@endcomponent

**Keystone Capital Partners** — Capital. Partnership. Growth.
*NCR Registered Credit Provider*

@endcomponent
