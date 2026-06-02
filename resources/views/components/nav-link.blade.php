@props(['active' => false])

<a {{ $attributes->merge(['class' => 'kc-nav-item' . ($active ? ' active' : '')]) }}>
    {{ $slot }}
</a>
