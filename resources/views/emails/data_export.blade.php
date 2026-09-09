@component('mail::message')

# Data export — {{ $profileName }}

The scheduled data export for **{{ $period }}** is attached.

**File:** {{ $fileName }}
&nbsp;|&nbsp; **Records:** {{ number_format($rowCount) }}

This file was generated automatically by Keystone Capital Partners. If you were
not expecting it, please contact us before opening the attachment.

**Keystone Capital Partners**
*NCR Registered Credit Provider*

@endcomponent
