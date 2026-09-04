<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Loan Products</span>
    <p class="kc-page-subtitle">Amounts, terms, rates and fees clients can apply under</p>
  </x-slot>

  @php $canManage = Auth::user()->hasRole('admin', 'finance', 'it_admin'); @endphp

  @if(session('success'))
    <div class="kc-alert-success mb-5">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="kc-alert-error mb-5">{{ session('error') }}</div>
  @endif

  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">All Products</h4>
      @if($canManage)
      <a href="{{ route('loan-products.create') }}" class="kc-btn-primary text-sm">New Product</a>
      @endif
    </div>

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr>
            <th>Name</th><th>Code</th><th>Amount Range</th><th>Term (months)</th>
            <th>Monthly Rate</th><th>Enhanced Affordability</th><th>Status</th>
            @if($canManage)<th>Actions</th>@endif
          </tr>
        </thead>
        <tbody>
          @forelse($products as $product)
          <tr>
            <td data-label="Name" class="font-semibold">{{ $product->name }}</td>
            <td data-label="Code" class="text-xs font-mono text-kc-charcoal/60">{{ $product->code }}</td>
            <td data-label="Amount Range" class="text-xs">R{{ number_format($product->min_amount, 0) }} – R{{ number_format($product->max_amount, 0) }}</td>
            <td data-label="Term" class="text-xs">
              {{ $product->min_months }}–{{ $product->max_months }}
              @if($product->max_months > 1)
                <span class="kc-badge kc-badge-navy text-[10px] ml-1">Multi-month</span>
              @endif
            </td>
            <td data-label="Monthly Rate" class="text-xs">{{ number_format($product->monthly_interest_rate * 100, 2) }}%</td>
            <td data-label="Enhanced Affordability" class="text-xs">
              {{ $product->requires_enhanced_affordability ? 'Required' : '—' }}
            </td>
            <td data-label="Status">
              <span class="kc-badge {{ $product->active ? 'kc-badge-green' : 'kc-badge-silver' }}">
                {{ $product->active ? 'Active — all clients' : 'Inactive' }}
              </span>
            </td>
            @if($canManage)
            <td data-label="Actions">
              <div class="flex items-center gap-2">
                <a href="{{ route('loan-products.edit', $product) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Edit</a>
                <form method="POST" action="{{ route('loan-products.toggle-active', $product) }}">
                  @csrf
                  <button type="submit" class="kc-btn-ghost text-[10px] py-1 px-2"
                    onclick="return confirm('{{ $product->active ? 'Deactivate' : 'Activate' }} \'{{ $product->name }}\' for all clients?')">
                    {{ $product->active ? 'Deactivate' : 'Activate' }}
                  </button>
                </form>
              </div>
            </td>
            @endif
          </tr>
          @empty
          <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="text-center py-8 text-kc-charcoal/60">No loan products configured yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <p class="text-[11px] text-kc-charcoal/60 mt-3">
      "Active" products are offered to every client. To open a multi-month product to one
      specific client without activating it for everyone, grant that override from the client's
      profile page instead ("Extended Terms Eligible").
    </p>
  </div>
</x-app-layout>
