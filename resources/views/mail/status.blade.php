@component('mail::message')
{{-- Email Header --}}
# {{ $subject ?? 'Loan Update' }}

{{-- Highlighted Status --}}
@if(isset($loan) && $loan->status)
@php
    $statusColors = [
        'pending' => 'yellow',
        'approved' => 'green',
        'rejected' => 'red',
        'archived' => 'gray',
    ];
    $color = $statusColors[strtolower($loan->status)] ?? 'blue';
@endphp

@component('mail::panel', ['color' => $color])
**Loan Status:** {{ ucfirst($loan->status) }}
@endcomponent
@endif

{{-- Body Lines --}}
@isset($bodyLines)
@foreach($bodyLines as $line)
{{ $line }}

@endforeach
@endisset

{{-- Loan Details --}}
@if(isset($loan))
---
**Loan Reference:** #{{ $loan->id }}  
**Amount:** R{{ number_format($loan->loan_amount, 2) }}  
**Type:** {{ ucfirst($loan->loan_type) }}
@endif

{{-- Optional button --}}
@if(isset($actionUrl) && isset($actionText))
@component('mail::button', ['url' => $actionUrl])
{{ $actionText }}
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
