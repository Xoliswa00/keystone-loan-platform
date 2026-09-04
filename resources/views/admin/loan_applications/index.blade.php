<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Loan Applications</span>
    <p class="kc-page-subtitle">{{ ucfirst(str_replace('_',' ',$statusFilter)) }} · {{ $pendingApplications->total() }} application(s)</p>
  </x-slot>

  {{-- Status filter — this is the only staff-facing application list, so it's
       also how staff reach an already-approved application (e.g. to use
       "Reverse Approval" on the show page) rather than only ever seeing
       'pending' ones. --}}
  <div class="flex flex-wrap gap-2 mb-4">
    @foreach(['pending' => 'Pending', 'under_review' => 'Under Review', 'affordability_review' => 'Affordability Review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
    <a href="{{ route('admin.loans', ['status' => $value]) }}"
       class="kc-badge text-xs {{ $statusFilter === $value ? 'kc-badge-navy' : 'kc-badge-silver' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="kc-card">
    @if(Auth::user()->hasRole('loan_officer', 'it_admin'))
    <div id="bulk-action-bar" class="hidden mb-4 flex items-center gap-3 kc-alert-success">
      <span><span id="bulk-selected-count">0</span> selected</span>
      <form method="POST" action="{{ route('loans.bulkApprove') }}" id="bulk-approve-form" class="inline">
        @csrf
        <div id="bulk-approve-ids"></div>
        <button type="submit" class="kc-btn-primary text-xs py-1 px-3"
                onclick="return confirm('Approve all selected applications?')">Approve Selected</button>
      </form>
      <button type="button" class="kc-btn-ghost text-xs py-1 px-3" onclick="document.getElementById('bulk-reject-modal').classList.remove('hidden')">Reject Selected</button>
    </div>

    <div id="bulk-reject-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="font-semibold text-lg mb-3">Reject Selected Applications</h3>
        <form method="POST" action="{{ route('loans.bulkReject') }}">
          @csrf
          <div id="bulk-reject-ids"></div>
          <label class="kc-label">Rejection reason (applies to all selected)</label>
          <textarea name="rejection_reason" required maxlength="1000" rows="3" class="kc-input w-full"></textarea>
          <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="kc-btn-ghost text-xs py-2 px-4" onclick="document.getElementById('bulk-reject-modal').classList.add('hidden')">Cancel</button>
            <button type="submit" class="kc-btn-primary text-xs py-2 px-4">Reject Selected</button>
          </div>
        </form>
      </div>
    </div>
    @endif

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th><input type="checkbox" id="select-all-applications" aria-label="Select all applications"></th>
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
            <td><input type="checkbox" class="application-checkbox" value="{{ $app->id }}" aria-label="Select application #{{ $app->id }}"></td>
            <td data-label="Ref" class="font-mono text-xs text-kc-charcoal/60">#{{ str_pad($app->id,6,'0',STR_PAD_LEFT) }}</td>
            <td data-label="Client">
              <div class="font-semibold">{{ $app->user?->name }}</div>
              <div class="text-xs text-kc-charcoal/60">{{ $app->user?->customer?->customer_code }}</div>
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
            <td data-label="Submitted" class="text-xs text-kc-charcoal/60">{{ $app->created_at->format('d M Y') }}</td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst($app->status) }}</span></td>
            <td data-label="Action"><a href="{{ route('Admin.show', $app->id) }}" class="kc-btn-ghost text-xs py-1 px-3">Review</a></td>
          </tr>
          @empty
          <tr><td colspan="9" class="text-center py-10 text-kc-charcoal/60">No pending applications.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $pendingApplications->links() }}</div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const selectAll = document.getElementById('select-all-applications');
      const checkboxes = document.querySelectorAll('.application-checkbox');
      const bar = document.getElementById('bulk-action-bar');
      const countEl = document.getElementById('bulk-selected-count');
      const approveIdsEl = document.getElementById('bulk-approve-ids');
      const rejectIdsEl = document.getElementById('bulk-reject-ids');

      function selectedIds() {
        return Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
      }

      function refresh() {
        const ids = selectedIds();
        bar.classList.toggle('hidden', ids.length === 0);
        countEl.textContent = ids.length;
        approveIdsEl.innerHTML = ids.map(id => `<input type="hidden" name="application_ids[]" value="${id}">`).join('');
        rejectIdsEl.innerHTML = ids.map(id => `<input type="hidden" name="application_ids[]" value="${id}">`).join('');
      }

      selectAll?.addEventListener('change', function () {
        checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
        refresh();
      });

      checkboxes.forEach(cb => cb.addEventListener('change', refresh));
    });
  </script>
</x-app-layout>
