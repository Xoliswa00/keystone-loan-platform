<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'kc-btn-primary'
]) }}>
    {{ $slot }}
</button>
