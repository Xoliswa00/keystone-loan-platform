@component('mail::message')

# {{ $subjectLine ?? $subject ?? 'Notification' }}

@isset($bodyLines)
@foreach($bodyLines as $line)
{{ $line }}

@endforeach
@endisset

@if(isset($loan) && $loan->loan_amount ?? false)
---
**Reference:** #{{ str_pad($loan->id ?? 0, 6, '0', STR_PAD_LEFT) }}
&nbsp;|&nbsp; **Amount:** R{{ number_format($loan->loan_amount ?? 0, 2) }}
@endif

@component('mail::button', ['url' => config('app.url')])
Open Dashboard
@endcomponent

**Keystone Capital Partners** — Capital. Partnership. Growth.
*NCR Registered Credit Provider*

@endcomponent
