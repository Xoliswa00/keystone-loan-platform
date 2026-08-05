@component('mail::message')

# Your access code

Use this code to access the investor portal. It expires in 10 minutes and can only be used once.

@component('mail::panel')
<div style="font-size: 28px; letter-spacing: 6px; text-align: center; font-weight: bold;">{{ $code }}</div>
@endcomponent

If you didn't request this, you can ignore this email — no one can access your investor account without also entering this code.

**Keystone Capital Partners** — Capital. Partnership. Growth.
*NCR Registered Credit Provider*

@endcomponent
