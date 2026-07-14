<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">New Loan Product</span>
    <p class="kc-page-subtitle">Configure amount, term, rate and fee rules for a new product</p>
  </x-slot>

  <div class="kc-card max-w-3xl">
    @include('admin.loan_products._form', [
      'product' => new \App\Models\LoanProduct,
      'action' => route('loan-products.store'),
      'method' => 'POST',
    ])
  </div>
</x-app-layout>
