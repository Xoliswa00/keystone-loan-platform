<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Loan Applications</span>
    <p class="kc-page-subtitle">Pending review · {{ $pendingApplications->total() }} application(s)</p>
  </x-slot>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Ref</th><th>Client</th><th>Amount</th><th>Product</th><th>Affordability</th><th>Submitted</th><th>Status</th><th>Action</th>
        </tr></thead>
        <tbody>
          @forelse($pendingApplications as $app)
          @php
            $passes = ($app->affordability_instalment_requested ?? 0) <= ($app->affordability_max_instalment ?? 1);
            $sc = match(strtolower($app->status ?? 'pending')) {
              'approved','disbursed'=>'kc-badge-green','under_review'=>'kc-badge-navy',
              'rejected'=>'kc-badge-red', default=>'kc-badge-gold'
            };
          @endphp
          <tr>
            <td data-label="Ref" class="font-mono text-xs text-kc-charcoal/50">#{{ str_pad($app->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Client">
              <div class="font-semibold">{{ $app->user?->name }}</div>
              <div class="text-xs text-kc-charcoal/40">{{ $app->user?->customer?->customer_code }}</div>
            </td>
            <td data-label="Amount" class="font-semibold">R {{ number_format($app->loan_amount,2) }}</td>
            <td data-label="Product"><span class="kc-badge kc-badge-silver">{{ $app->product?->name ?? 'Standard' }}</span></td>
            <td data-label="Affordability">
              @if($app->affordability_checked)
                <span class="kc-badge {{ $passes ? 'kc-badge-green' : 'kc-badge-red' }}">{{ $passes ? 'Pass' : 'Fail' }}</span>
              @else
                <span class="kc-badge kc-badge-silver">Pending</span>
              @endif
            </td>
            <td data-label="Submitted" class="text-xs text-kc-charcoal/50">{{ $app->created_at->format('d M Y') }}</td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst($app->status) }}</span></td>
            <td data-label="Action"><a href="{{ route('Admin.show', $app->id) }}" class="kc-btn-ghost text-xs py-1 px-3">Review</a></td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center py-10 text-kc-charcoal/40">No pending applications.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $pendingApplications->links() }}</div>
  </div>
</x-app-layout>
