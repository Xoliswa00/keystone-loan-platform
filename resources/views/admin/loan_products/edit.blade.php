<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Edit {{ $product->name }}</span>
    <p class="kc-page-subtitle">Code: {{ $product->code }}</p>
  </x-slot>

  <div class="kc-card max-w-3xl">
    @include('admin.loan_products._form', [
      'product' => $product,
      'action' => route('loan-products.update', $product),
      'method' => 'PUT',
    ])
  </div>
</x-app-layout>
