<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Client Management</span>
    <p class="kc-page-subtitle">{{ $stats['total'] }} registered clients</p>
  </x-slot>

  {{-- Stats --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Total Clients</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $stats['total'] }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Active Loans</p>
      <p class="font-display text-2xl font-bold text-kc-navy mt-1">{{ $stats['active'] }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">With Balances</p>
      <p class="font-display text-2xl font-bold {{ $stats['overdue'] > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ $stats['overdue'] }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/60 uppercase tracking-wider">Restricted</p>
      <p class="font-display text-2xl font-bold text-orange-500 mt-1">{{ $stats['restricted'] }}</p>
    </div>
  </div>

  {{-- Search + filters --}}
  <form method="GET" class="flex flex-wrap gap-3 mb-4">
    <div class="flex-1 min-w-48">
      <input type="text" name="search" value="{{ request('search') }}" class="kc-input"
        placeholder="Search by name, ID, phone, email, code...">
    </div>
    <select name="filter" class="kc-select w-40">
      <option value="">All clients</option>
      <option value="active"     {{ request('filter')==='active'     ?'selected':'' }}>Active loans</option>
      <option value="overdue"    {{ request('filter')==='overdue'    ?'selected':'' }}>Outstanding balance</option>
      <option value="restricted" {{ request('filter')==='restricted' ?'selected':'' }}>Restricted</option>
    </select>
    <button type="submit" class="kc-btn-primary">Search</button>
    @if(request()->hasAny(['search','filter']))
    <a href="{{ route('customers.index') }}" class="kc-btn-ghost">Clear</a>
    @endif
  </form>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Code</th><th>Name</th><th>ID Number</th><th>Phone</th>
          <th>Active Loans</th><th>Outstanding</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @forelse($customers as $c)
          @php
            $hasRestriction = $c->applications_restricted || $c->blacklisted;
            $statusClass = $c->blacklisted ? 'kc-badge-red'
              : ($c->applications_restricted ? 'kc-badge-gold'
              : ($c->user_status === 'active' ? 'kc-badge-green' : 'kc-badge-silver'));
            $statusLabel = $c->blacklisted ? 'Blacklisted'
              : ($c->applications_restricted ? 'Restricted'
              : ucfirst($c->user_status ?? 'active'));
          @endphp
          <tr class="{{ $hasRestriction ? 'bg-amber-50/50' : '' }}">
            <td data-label="Code" class="font-mono text-xs text-kc-charcoal/60">{{ $c->customer_code }}</td>
            <td data-label="Name">
              <div class="font-semibold">{{ $c->name }}</div>
              <div class="text-xs text-kc-charcoal/60">{{ $c->email }}</div>
            </td>
            <td data-label="ID" class="font-mono text-xs">{{ $c->ID_Number }}</td>
            <td data-label="Phone" class="text-xs">{{ $c->phone }}</td>
            <td data-label="Loans">
              @if($c->active_loans_count > 0)
                <span class="kc-badge kc-badge-gold">{{ $c->active_loans_count }}</span>
              @else
                <span class="text-kc-charcoal/60 text-xs">—</span>
              @endif
            </td>
            <td data-label="Outstanding" class="{{ $c->current_balance > 0 ? 'font-semibold text-kc-navy' : 'text-kc-charcoal/60' }}">
              {{ $c->current_balance > 0 ? 'R '.number_format($c->current_balance,2) : '—' }}
            </td>
            <td data-label="Status"><span class="kc-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
            <td data-label="Actions">
              <a href="{{ route('customers.show', $c) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View →</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/60">
            @if(request('search')) No clients match "{{ request('search') }}" @else No clients yet. @endif
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $customers->withQueryString()->links() }}</div>
  </div>
</x-app-layout>
