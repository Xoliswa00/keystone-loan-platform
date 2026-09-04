<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Disbursement Approvals</span>
    <p class="kc-page-subtitle">{{ $disbursements->count() }} loan(s) waiting for fund release</p>
  </x-slot>

  @if($disbursements->isNotEmpty())
  <div class="flex justify-end mb-4">
    <form method="POST" action="{{ route('disbursements.approveAll') }}">
      @csrf
      <button type="submit" class="kc-btn-primary"
        onclick="return confirm('Approve ALL {{ $disbursements->count() }} disbursements and post to GL?')">
        Approve All + GL Post
      </button>
    </form>
  </div>
  @endif

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>ID</th><th>Client</th><th>Loan</th><th>Amount</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse($disbursements as $disb)
          <tr>
            <td data-label="ID" class="font-mono text-xs">#{{ str_pad($disb->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Client">
              <div class="font-semibold">{{ $disb->loan?->user?->name ?? '—' }}</div>
              <div class="text-xs text-kc-charcoal/70">{{ $disb->payment_reference }}</div>
            </td>
            <td data-label="Loan" class="font-mono text-xs">#{{ str_pad($disb->loan_id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Amount" class="font-semibold text-kc-navy">R {{ number_format($disb->disbursed_amount,2) }}</td>
            <td data-label="Status"><span class="kc-badge kc-badge-gold">{{ ucfirst(str_replace('_',' ',$disb->status)) }}</span></td>
            <td data-label="Created" class="text-xs text-kc-charcoal/70">{{ $disb->created_at->format('d M Y') }}</td>
            <td data-label="Actions">
              <div class="flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('disbursements.approve', $disb->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="kc-btn-primary text-xs py-1 px-3"
                    onclick="return confirm('Approve and post to GL?')">Approve</button>
                </form>
                <form method="POST" action="{{ route('disbursements.reject', $disb->id) }}" class="inline">
                  @csrf
                  <input type="hidden" name="rejection_reason" id="rej_{{ $disb->id }}" value="">
                  <button type="button" class="kc-btn-ghost text-xs py-1 px-3 border-red-200 text-red-600"
                    onclick="let r=prompt('Rejection reason:');if(r){document.getElementById('rej_{{ $disb->id }}').value=r;this.closest('form').submit()}">Reject</button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center py-10 text-kc-charcoal/70">No pending disbursements.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
