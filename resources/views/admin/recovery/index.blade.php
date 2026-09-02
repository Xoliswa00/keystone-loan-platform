<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Debt Recovery Cases</span>
    <p class="kc-page-subtitle">Tracking recovery on written-off and NPL accounts</p>
  </x-slot>

  {{-- Summary KPIs --}}
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Total Cases</span>
      <p class="font-display text-2xl font-semibold text-kc-navy mt-2">{{ $summary->total_cases ?? 0 }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Written Off</span>
      <p class="font-display text-2xl font-semibold text-red-600 mt-2">R {{ number_format($summary->total_written_off ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Recovered</span>
      <p class="font-display text-2xl font-semibold text-emerald-600 mt-2">R {{ number_format($summary->total_recovered ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">Still Outstanding</span>
      <p class="font-display text-2xl font-semibold text-orange-600 mt-2">R {{ number_format($summary->total_outstanding ?? 0, 2) }}</p>
    </div>
    <div class="kc-stat-card">
      <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">In Legal</span>
      <p class="font-display text-2xl font-semibold text-kc-navy mt-2">{{ $summary->in_legal ?? 0 }}</p>
    </div>
  </div>

  {{-- Filter --}}
  <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <select name="status" class="kc-select">
      <option value="">All statuses</option>
      @foreach(\App\Models\DebtRecovery::STATUSES as $val => $info)
        <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $info['label'] }}</option>
      @endforeach
    </select>
    <button type="submit" class="kc-btn-ghost">Filter</button>
  </form>

  <div class="kc-card">
    <table class="kc-table">
      <thead><tr>
        <th>Loan</th><th>Client</th><th>Opened</th><th>Written Off</th><th>Recovered</th>
        <th>Recovery %</th><th>Status</th><th>Next Action</th><th>Assigned</th><th></th>
      </tr></thead>
      <tbody>
        @forelse($cases as $case)
        @php
          $statusInfo = \App\Models\DebtRecovery::STATUSES[$case->status] ?? ['label'=>$case->status,'class'=>'kc-badge-silver'];
          $isOverdue  = $case->next_action_date && $case->next_action_date->isPast() && $case->status === 'open';
        @endphp
        <tr class="{{ $isOverdue ? 'bg-red-50' : '' }}">
          <td class="font-mono text-xs">#{{ str_pad($case->loan_id, 6, '0', STR_PAD_LEFT) }}</td>
          <td>
            <div class="font-semibold">{{ $case->user?->name }}</div>
            <div class="text-xs text-kc-charcoal/40">{{ $case->user?->customer?->customer_code }}</div>
          </td>
          <td class="text-xs">{{ $case->opened_at->format('d M Y') }}</td>
          <td class="font-semibold text-red-600">R {{ number_format($case->original_write_off_amount, 2) }}</td>
          <td class="font-semibold text-emerald-600">R {{ number_format($case->total_recovered, 2) }}</td>
          <td>
            <div class="flex items-center gap-2">
              <div class="w-16 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" style="width:{{ min(100, $case->recoveryRate()) }}%"></div>
              </div>
              <span class="text-xs">{{ $case->recoveryRate() }}%</span>
            </div>
          </td>
          <td><span class="kc-badge {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span></td>
          <td class="text-xs {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-kc-charcoal/50' }}">
            {{ $case->next_action_date?->format('d M Y') ?? '—' }}
          </td>
          <td class="text-xs">{{ $case->assignedTo?->name ?? '—' }}</td>
          <td><a href="{{ route('admin.recovery.show', $case) }}" class="text-xs text-kc-gold hover:text-kc-gold-muted">Manage →</a></td>
        </tr>
        @empty
        <tr><td colspan="10" class="text-center text-kc-charcoal/40 py-8">No recovery cases.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="mt-4">{{ $cases->links() }}</div>
  </div>
</x-app-layout>
