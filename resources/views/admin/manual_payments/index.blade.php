<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Payment Verification</span>
    <p class="kc-page-subtitle">Client-submitted proof of payment awaiting review · {{ $pendingPayments->total() }} pending</p>
  </x-slot>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Client</th><th>Schedule</th><th>Amount Claimed</th><th>Payment Date</th>
          <th>Reference</th><th>Proof</th><th>Submitted</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @forelse($pendingPayments as $p)
          <tr>
            <td data-label="Client">
              <div class="font-semibold">{{ $p->user?->name ?? '—' }}</div>
              <div class="text-xs text-kc-charcoal/60">{{ $p->user?->customer?->customer_code }}</div>
            </td>
            <td data-label="Schedule" class="text-xs">
              Instalment #{{ $p->repaymentSchedule?->installment_number ?? '—' }}
              — due {{ $p->repaymentSchedule?->due_date ? \Carbon\Carbon::parse($p->repaymentSchedule->due_date)->format('d M Y') : '—' }}
            </td>
            <td data-label="Amount" class="font-semibold text-kc-navy">R {{ number_format($p->payment_amount, 2) }}</td>
            <td data-label="Payment Date" class="text-xs">{{ \Carbon\Carbon::parse($p->payment_date)->format('d M Y') }}</td>
            <td data-label="Reference" class="text-xs text-kc-charcoal/60">{{ $p->payment_reference ?? '—' }}</td>
            <td data-label="Proof">
              @if($p->proof_of_payment_path)
              <a href="{{ route('secure-documents.repayment-proof', $p) }}"
                 target="_blank" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View</a>
              @else
              <span class="text-xs text-kc-charcoal/60">—</span>
              @endif
            </td>
            <td data-label="Submitted" class="text-xs text-kc-charcoal/60">
              {{ $p->submittedBy?->name ?? 'Client' }} · {{ $p->created_at->format('d M Y') }}
            </td>
            <td data-label="Actions">
              <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.manual-payments.approve', $p) }}" class="inline">
                  @csrf
                  <button type="submit" class="kc-btn-primary text-xs py-1 px-3"
                    onclick="return confirm('Verify this payment and post it to the GL?')">Approve</button>
                </form>
                <div x-data="{open:false}" class="inline">
                  <button type="button" @click="open=!open" class="kc-btn-ghost text-xs py-1 px-3 text-red-600">Reject</button>
                  <div x-show="open" x-transition class="absolute mt-8 z-10 bg-white border border-kc-silver-light rounded-lg p-3 shadow-lg w-64">
                    <form method="POST" action="{{ route('admin.manual-payments.reject', $p) }}">
                      @csrf
                      <textarea name="rejection_reason" rows="2" required maxlength="500"
                        class="kc-input w-full text-xs" placeholder="Reason (required)"></textarea>
                      <button type="submit" class="kc-btn-danger w-full justify-center text-xs mt-2">Confirm Reject</button>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/60">No payments awaiting verification.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $pendingPayments->links() }}</div>
  </div>
</x-app-layout>
