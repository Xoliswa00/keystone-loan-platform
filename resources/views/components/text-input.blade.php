@props(['disabled' => false])

<input
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge(['class' => 'kc-input' . ($disabled ? ' opacity-50 cursor-not-allowed' : '')]) !!}
>
