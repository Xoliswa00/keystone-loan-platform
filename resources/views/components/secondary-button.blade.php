<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'kc-btn-ghost'
]) }}>
    {{ $slot }}
</button>
