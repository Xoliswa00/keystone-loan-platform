<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Reviewer — {{ $reviewer?->name ?? 'Reviewer' }}</span>
    <p class="kc-page-subtitle">Individual performance and portfolio governance</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

      @if($trends->isNotEmpty())
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Monthly Trend</h4>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Month</th><th>Approval Rate</th><th>Default Rate</th></tr></thead>
            <tbody>
              @foreach($trends as $t)
              <tr>
                <td data-label="Month">{{ $t->review_month }}</td>
                <td data-label="Approval Rate">
                  <div class="flex items-center gap-2">
                    <div class="w-16 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                      <div class="h-full bg-emerald-500 rounded-full" style="width:{{ min(100, $t->approval_rate??0) }}%"></div>
                    </div>
                    {{ $t->approval_rate??0 }}%
                  </div>
                </td>
                <td data-label="Default" class="{{ ($t->default_rate??0)>5?'text-red-600 font-bold':'text-emerald-600' }}">{{ $t->default_rate??0 }}%</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Loans by Status</h4>
        @foreach($loans as $status => $group)
        <p class="text-xs font-semibold text-kc-charcoal/60 uppercase tracking-wider mb-2 mt-3">{{ ucfirst($status ?: 'Unknown') }} ({{ $group->count() }})</p>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Ref</th><th>Customer</th><th>Balance</th><th>Date</th></tr></thead>
            <tbody>
              @foreach($group->take(5) as $row)
              <tr>
                <td data-label="Ref" class="font-mono text-xs">#{{ str_pad($row->application_id,6,'0',STR_PAD_LEFT) }}</td>
                <td data-label="Customer">{{ $row->customer_code??'—' }}</td>
                <td data-label="Balance">R {{ number_format($row->remaining_balance??0,2) }}</td>
                <td data-label="Date" class="text-xs text-kc-charcoal/50">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y') }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endforeach
      </div>
    </div>

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Defaults</h4>
      @forelse($defaults as $d)
      <div class="p-3 rounded-lg border border-red-200 bg-red-50 mb-2">
        <p class="font-semibold text-sm">{{ $d->name }}</p>
        <p class="text-xs text-kc-charcoal/50">{{ $d->customer_code??'—' }}</p>
        <p class="text-xs font-semibold text-red-600 mt-1">R {{ number_format($d->remaining_balance??0,2) }}</p>
      </div>
      @empty
      <p class="text-sm text-kc-charcoal/40 text-center py-4">No defaults.</p>
      @endforelse
    </div>
  </div>
</x-app-layout>
