<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Credit Report Disputes</span>
    <p class="kc-page-subtitle">Disputed accounts are held back from outbound feeds until resolved</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  <div class="kc-card mb-6 max-w-2xl">
    <h4 class="font-display font-semibold text-kc-navy mb-3">Log a dispute</h4>
    <form method="POST" action="{{ route('admin.exports.disputes.store') }}" class="space-y-3">
      @csrf
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="kc-label">Loan ID</label>
          <input type="number" name="loan_id" value="{{ old('loan_id') }}" required class="kc-input">
          @error('loan_id')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
          <label class="kc-label">Reference (optional)</label>
          <input type="text" name="reference" value="{{ old('reference') }}" class="kc-input" placeholder="Consumer / bureau case ref">
        </div>
      </div>
      <div>
        <label class="kc-label">Reason</label>
        <textarea name="reason" rows="2" required class="kc-input">{{ old('reason') }}</textarea>
        @error('reason')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>
      <button type="submit" class="kc-btn-primary text-sm">Log dispute</button>
    </form>
  </div>

  <div class="kc-card">
    <h4 class="font-display font-semibold text-kc-navy mb-4">All disputes</h4>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr><th>Loan</th><th>Borrower</th><th>Raised</th><th>Respond by</th><th>Reason</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          @forelse($disputes as $d)
            <tr>
              <td data-label="Loan" class="text-xs font-mono">#{{ $d->loan_id }}</td>
              <td data-label="Borrower" class="text-xs">{{ $d->loan?->user?->name ?? '—' }}</td>
              <td data-label="Raised" class="text-xs">{{ $d->raised_at?->format('Y-m-d') }}</td>
              <td data-label="Respond by" class="text-xs {{ $d->isOverdue() ? 'text-red-600 font-semibold' : '' }}">
                {{ $d->respond_by?->format('Y-m-d') }}{{ $d->isOverdue() ? ' (overdue)' : '' }}
              </td>
              <td data-label="Reason" class="text-xs max-w-xs truncate" title="{{ $d->reason }}">{{ $d->reason }}</td>
              <td data-label="Status"><span class="kc-badge {{ \App\Models\CreditReportDispute::STATUS_CLASSES[$d->status] ?? 'kc-badge-silver' }}">{{ $d->status }}</span></td>
              <td data-label="Action">
                @if($d->status === 'open')
                  <details>
                    <summary class="text-xs text-kc-navy cursor-pointer underline underline-offset-2">Resolve</summary>
                    <form method="POST" action="{{ route('admin.exports.disputes.resolve', $d) }}" class="mt-2 space-y-2">
                      @csrf
                      <textarea name="resolution_note" rows="2" required class="kc-input text-xs" placeholder="What was investigated / corrected"></textarea>
                      <button type="submit" class="kc-btn-ghost text-[10px] py-1 px-2">Mark resolved</button>
                    </form>
                  </details>
                @else
                  <span class="text-[11px] text-kc-charcoal/60">{{ $d->resolved_at?->format('Y-m-d') }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-8 text-kc-charcoal/70">No disputes logged.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $disputes->links() }}</div>
  </div>
</x-app-layout>
