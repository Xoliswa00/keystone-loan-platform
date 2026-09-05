<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">My Applications</span>
    <p class="kc-page-subtitle">{{ $Applications->total() }} application(s)</p>
  </x-slot>

  <div class="flex justify-end mb-4">
    <a href="{{ route('loanapplications.create') }}" class="kc-btn-primary">+ New Application</a>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Ref</th><th>Amount</th><th>Type</th><th>Product</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
          @forelse($Applications as $app)
          @php $sc = match(strtolower($app->status??'')){'approved','disbursed','settled'=>'kc-badge-green','rejected'=>'kc-badge-red','pending'=>'kc-badge-gold','under_review'=>'kc-badge-navy',default=>'kc-badge-silver'}; @endphp
          <tr>
            <td data-label="Ref" class="font-mono text-xs">#{{ str_pad($app->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Amount" class="font-semibold">R {{ number_format($app->loan_amount,2) }}</td>
            <td data-label="Type">{{ ucfirst($app->loan_type) }}</td>
            <td data-label="Product"><span class="kc-badge kc-badge-silver">{{ $app->product?->name ?? 'Standard' }}</span></td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst($app->status) }}</span></td>
            <td data-label="Submitted" class="text-xs text-kc-charcoal/70">{{ $app->created_at->format('d M Y') }}</td>
            <td data-label=""><a href="{{ route('loanapplications.show', $app->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View →</a></td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center py-10 text-kc-charcoal/70">No applications yet. <a href="{{ route('loanapplications.create') }}" class="text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Apply now →</a></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $Applications->links() }}</div>
  </div>
</x-app-layout>
