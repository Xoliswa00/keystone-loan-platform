<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Funding Facilities</span>
    <p class="kc-page-subtitle">Capital liabilities — investor and lender accounts</p>
  </x-slot>

  <div class="flex justify-between items-center mb-6">
    <div class="flex gap-3">
      <form method="POST" action="{{ route('admin.funding.accrue-all') }}">
        @csrf
        <button type="submit" class="kc-btn-ghost text-sm"
          onclick="return confirm('Accrue this month\'s interest on all active facilities?')">
          Accrue Monthly Interest
        </button>
      </form>
    </div>
    <a href="{{ route('admin.funding.create') }}" class="kc-btn-primary">+ New Facility</a>
  </div>

  {{-- Summary KPIs --}}
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Total Facilities</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $summary['total_facilities'] }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Total Limit</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">R {{ number_format($summary['total_limit'],0) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Outstanding (Liabilities)</p>
      <p class="font-display text-2xl font-bold text-red-600 mt-1">R {{ number_format($summary['total_drawn'],2) }}</p>
      <p class="text-xs text-kc-charcoal/70 mt-0.5">On balance sheet</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Accrued Interest</p>
      <p class="font-display text-2xl font-bold text-orange-600 mt-1">R {{ number_format($summary['total_accrued_int'],2) }}</p>
    </div>
    <div class="kc-stat-card">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Active</p>
      <p class="font-display text-2xl font-bold text-emerald-600 mt-1">{{ $summary['active_count'] }}</p>
    </div>
  </div>

  {{-- Facilities table --}}
  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Code</th><th>Facility Name</th><th>Type</th><th>Funder</th>
          <th>Limit</th><th>Outstanding</th><th>Utilisation</th>
          <th>Rate p.a.</th><th>Accrued Int.</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @forelse($facilities as $f)
          @php $sc = \App\Models\FundingFacility::STATUS_CLASSES[$f->status] ?? 'kc-badge-silver'; @endphp
          <tr>
            <td data-label="Code" class="font-mono text-xs font-semibold text-kc-navy">{{ $f->facility_code }}</td>
            <td data-label="Name" class="font-semibold">{{ $f->facility_name }}</td>
            <td data-label="Type" class="text-xs"><span class="kc-badge kc-badge-silver">{{ \App\Models\FundingFacility::FUNDER_TYPES[$f->funder_type] ?? $f->funder_type }}</span></td>
            <td data-label="Funder">
              <div>{{ $f->funder_name }}</div>
              <div class="text-xs text-kc-charcoal/70">{{ $f->contact_person }}</div>
            </td>
            <td data-label="Limit">R {{ number_format($f->facility_limit,2) }}</td>
            <td data-label="Outstanding" class="font-semibold text-red-600">R {{ number_format($f->current_balance,2) }}</td>
            <td data-label="Utilisation">
              <div class="flex items-center gap-2">
                <div class="w-14 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                  <div class="h-full {{ $f->utilisationRate() > 90 ? 'bg-red-500' : 'bg-kc-gold' }} rounded-full"
                       style="width:{{ min(100,$f->utilisationRate()) }}%"></div>
                </div>
                <span class="text-xs">{{ $f->utilisationRate() }}%</span>
              </div>
            </td>
            <td data-label="Rate" class="text-xs">{{ round($f->interest_rate*100,2) }}%</td>
            <td data-label="Accrued" class="{{ $f->accrued_interest > 0 ? 'text-orange-600 font-semibold' : 'text-kc-charcoal/70' }}">
              R {{ number_format($f->accrued_interest,2) }}
            </td>
            <td data-label="Status"><span class="kc-badge {{ $sc }}">{{ ucfirst(str_replace('_',' ',$f->status)) }}</span></td>
            <td data-label="Actions">
              <a href="{{ route('admin.funding.show', $f) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Manage →</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="11" class="text-center py-10 text-kc-charcoal/70">
            No funding facilities yet. <a href="{{ route('admin.funding.create') }}" class="text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Add one →</a>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
