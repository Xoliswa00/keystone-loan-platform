<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Audit Log</span>
    <p class="kc-page-subtitle">Immutable record of all system state changes</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap gap-3 mb-6">
    <div>
      <label class="kc-label">Event</label>
      <select name="event" class="kc-select">
        <option value="">All events</option>
        @foreach(['created','updated','approved','rejected','disbursed','settled','note_added','assigned','collections_contact'] as $e)
          <option value="{{ $e }}" {{ request('event') === $e ? 'selected' : '' }}>{{ ucfirst($e) }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="kc-label">User</label>
      <select name="user_id" class="kc-select">
        <option value="">All users</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="kc-label">Model</label>
      <select name="model" class="kc-select">
        <option value="">All</option>
        <option value="Loan" {{ request('model') === 'Loan' ? 'selected' : '' }}>Loans</option>
        <option value="LoanApplication" {{ request('model') === 'LoanApplication' ? 'selected' : '' }}>Applications</option>
      </select>
    </div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Filter</button></div>
  </form>

  <div class="kc-card">
    <table class="kc-table">
      <thead><tr>
        <th>When</th><th>Event</th><th>Record</th><th>User</th><th>IP</th><th>Changes</th>
      </tr></thead>
      <tbody>
        @foreach($logs as $log)
        <tr>
          <td data-label="When" class="text-xs text-kc-charcoal/60">{{ $log->created_at->format('d M Y H:i') }}</td>
          <td data-label="Event"><span class="kc-badge kc-badge-navy">{{ $log->event }}</span></td>
          <td data-label="Record" class="text-xs font-mono">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
          <td data-label="User" class="text-xs">{{ $log->user?->name ?? 'System' }}</td>
          <td data-label="IP" class="text-xs text-kc-charcoal/60">{{ $log->ip_address }}</td>
          <td data-label="Changes" class="text-xs max-w-xs truncate text-kc-charcoal/60">
            @if($log->new_values)
              {{ implode(', ', array_map(fn($k,$v) => $k.': '.(is_array($v) ? json_encode($v) : $v), array_keys($log->new_values ?? []), array_values($log->new_values ?? []))) }}
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="mt-4">{{ $logs->links() }}</div>
  </div>
</x-app-layout>
