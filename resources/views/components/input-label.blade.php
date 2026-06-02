@props(['value'])

<label {{ $attributes->merge(['class' => 'kc-label']) }}>
    {{ $value ?? $slot }}
</label>
