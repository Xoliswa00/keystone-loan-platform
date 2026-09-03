<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Credit Governance Scorecard</span>
    <p class="kc-page-subtitle">Reviewer performance and portfolio risk by loan officer</p>
  </x-slot>

  {{-- Org-level KPIs --}}
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Total Outstanding</span>
      <p class="font-display text-2xl font-semibold text-kc-navy mt-2">R {{ number_format($totals['total_outstanding'], 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Total Risky Exposure</span>
      <p class="font-display text-2xl font-semibold text-red-600 mt-2">R {{ number_format($totals['total_risky'], 2) }}</p>
      <p class="text-xs text-kc-charcoal/60 mt-1">Written-off or 90+ DPD</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Avg Default Rate</span>
      <p class="font-display text-2xl font-semibold {{ $totals['avg_default_rate'] > 5 ? 'text-red-600' : 'text-emerald-600' }} mt-2">{{ number_format($totals['avg_default_rate'], 2) }}%</p>
    </div>
  </div>

  {{-- Reviewer scorecard table --}}
  <div class="kc-card mb-6">
    <h4 class="font-display font-semibold text-kc-navy mb-4">Reviewer Performance</h4>
    <div class="overflow-x-auto">
      <table class="kc-table">
        <thead><tr>
          <th>Reviewer</th><th>Reviewed</th><th>Approved</th><th>Rejected</th>
          <th>Approval Rate</th><th>Total Disbursed</th><th>Outstanding</th>
          <th>Risky Exposure</th><th>Default Rate</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($summary as $row)
          @php $riskClass = $row->default_rate > 10 ? 'text-red-600' : ($row->default_rate > 5 ? 'text-orange-500' : 'text-emerald-600'); @endphp
          <tr>
            <td class="font-semibold text-kc-navy">{{ $row->reviewer_name }}</td>
            <td>{{ $row->applications_reviewed }}</td>
            <td class="text-emerald-600">{{ $row->applications_approved }}</td>
            <td class="text-red-500">{{ $row->applications_rejected }}</td>
            <td>
              <div class="flex items-center gap-2">
                <div class="w-16 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                  <div class="h-full bg-kc-gold rounded-full" style="width:{{ min(100, $row->approval_rate) }}%"></div>
                </div>
                <span class="text-xs">{{ $row->approval_rate }}%</span>
              </div>
            </td>
            <td>R {{ number_format($row->total_disbursed, 2) }}</td>
            <td>R {{ number_format($row->outstanding, 2) }}</td>
            <td class="text-red-500 font-semibold">R {{ number_format($row->risky_exposure, 2) }}</td>
            <td class="{{ $riskClass }} font-bold">{{ $row->default_rate }}%</td>
            <td>
              <a href="{{ route('reports.reviewer', $row->id) }}" class="text-xs text-kc-gold hover:text-kc-gold-muted">Detail →</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="10" class="text-center text-kc-charcoal/60 py-8">No reviewed applications yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Org trend --}}
  @if($orgTrend->isNotEmpty())
  <div class="kc-card">
    <h4 class="font-display font-semibold text-kc-navy mb-4">Organisation Monthly Trend</h4>
    <table class="kc-table">
      <thead><tr><th>Month</th><th>Approval Rate</th><th>Default Rate</th></tr></thead>
      <tbody>
        @foreach($orgTrend as $t)
        <tr>
          <td>{{ $t->review_month }}</td>
          <td>
            <div class="flex items-center gap-2">
              <div class="w-20 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" style="width:{{ min(100, $t->approval_rate ?? 0) }}%"></div>
              </div>
              {{ $t->approval_rate ?? 0 }}%
            </div>
          </td>
          <td>
            <span class="{{ ($t->default_rate ?? 0) > 5 ? 'text-red-600' : 'text-emerald-600' }} font-semibold">
              {{ $t->default_rate ?? 0 }}%
            </span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</x-app-layout>
