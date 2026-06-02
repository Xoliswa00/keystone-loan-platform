<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Disbursements</span>
    <p class="kc-page-subtitle">All loan disbursements — approved, pending, and released</p>
  </x-slot>

  @if($disbursements->where('status','waiting_for_approval')->count() > 0)
  <div class="flex justify-end mb-4">
    <form method="POST" action="{{ route('disbursements.approveAll') }}">
      @csrf
      <button type="submit" class="kc-btn-primary"
        onclick="return confirm('Approve all pending disbursements and post to GL?')">
        Approve All Pending ({{ $disbursements->where('status','waiting_for_approval')->count() }})
      </button>
    </form>
  </div>
  @endif

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>ID</th><th>Client</th><th>Loan</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @forelse($disbursements as $disb)
          @php
            $sc = match($disb->status) {
              'released'           => 'kc-badge-green',
              'approved'           => 'kc-badge-navy',
              'waiting_for_approval'=> 'kc-badge-gold',
              'rejected'           => 'kc-badge-red',
              default              => 'kc-badge-silver',
            };
          @endphp
          <tr>
            <td data-label="ID" class="font-mono text-xs">#{{ str_pad($disb->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Client">
              <div class="font-semibold">{{ $disb->loan?->user?->name ?? '—' }}</div>
              <div class="text-xs text-kc-charcoal/40">{{ $disb->payment_reference }}</div>
            </td>
            <td data-label="Loan" class="font-mono text-xs">#{{ str_pad($disb->loan_id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Amount" class="font-semibold text-kc-gold">R {{ number_format($disb->disbursed_amount,2) }}</td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst(str_replace('_',' ',$disb->status)) }}</span></td>
            <td data-label="Date" class="text-xs text-kc-charcoal/50">
              {{ $disb->disbursement_date ? \Carbon\Carbon::parse($disb->disbursement_date)->format('d M Y') : $disb->created_at->format('d M Y') }}
            </td>
            <td data-label="Actions">
              @if($disb->status === 'waiting_for_approval')
              <div class="flex gap-2">
                <form method="POST" action="{{ route('disbursements.approve', $disb->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="kc-btn-primary text-xs py-1 px-3"
                    onclick="return confirm('Approve and post to GL?')">Approve</button>
                </form>
                <form method="POST" action="{{ route('disbursements.reject', $disb->id) }}" class="inline">
                  @csrf
                  <input type="hidden" name="rejection_reason" id="rej_{{ $disb->id }}" value="">
                  <button type="button" class="kc-btn-ghost text-xs py-1 px-3 text-red-600"
                    onclick="let r=prompt('Rejection reason:');if(r){document.getElementById('rej_{{ $disb->id }}').value=r;this.closest('form').submit()}">
                    Reject
                  </button>
                </form>
              </div>
              @elseif($disb->status === 'approved')
              <form method="POST" action="{{ route('disbursements.release', $disb->id) }}" class="inline">
                @csrf
                <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Release</button>
              </form>
              @else
              <span class="text-xs text-kc-charcoal/30">{{ ucfirst($disb->status) }}</span>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center py-10 text-kc-charcoal/40">No disbursements found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($disbursements,'links'))
    <div class="mt-4">{{ $disbursements->links() }}</div>
    @endif
  </div>
</x-app-layout>
