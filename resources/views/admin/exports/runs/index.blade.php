<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Export Runs</span>
    <p class="kc-page-subtitle">Every execution — the record of what was sent, when</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  <div class="kc-card">
    <form method="GET" class="flex flex-wrap gap-3 mb-4 text-xs">
      <select name="profile" class="kc-select text-xs">
        <option value="">All profiles</option>
        @foreach($profiles as $p)
          <option value="{{ $p->slug }}" @selected(request('profile') === $p->slug)>{{ $p->name }}</option>
        @endforeach
      </select>
      <select name="status" class="kc-select text-xs">
        <option value="">Any status</option>
        @foreach(array_keys(\App\Models\ExportRun::STATUS_CLASSES) as $s)
          <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <input type="text" name="period" value="{{ request('period') }}" placeholder="YYYY-MM" class="kc-input text-xs w-28 font-mono">
      <button type="submit" class="kc-btn-ghost text-xs">Filter</button>
    </form>

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr><th>When</th><th>Profile</th><th>Period</th><th>Gen</th><th>Rows</th><th>Transport</th><th>Status</th><th>Trigger</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($runs as $run)
            <tr>
              <td data-label="When" class="text-xs">{{ $run->created_at->format('Y-m-d H:i') }}</td>
              <td data-label="Profile" class="text-xs">{{ $run->profile?->name ?? '—' }}</td>
              <td data-label="Period" class="text-xs font-mono">{{ $run->period }}</td>
              <td data-label="Gen" class="text-xs">{{ $run->snapshot_generation ?? '—' }}</td>
              <td data-label="Rows" class="text-xs">{{ number_format($run->row_count) }}</td>
              <td data-label="Transport" class="text-xs">{{ $run->transport }}</td>
              <td data-label="Status"><span class="kc-badge {{ \App\Models\ExportRun::STATUS_CLASSES[$run->status] ?? 'kc-badge-silver' }}">{{ $run->status }}</span></td>
              <td data-label="Trigger" class="text-xs">{{ $run->triggered_by }}</td>
              <td data-label=""><a href="{{ route('admin.exports.runs.show', $run) }}" class="text-xs text-kc-navy underline underline-offset-2">View</a></td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center py-8 text-kc-charcoal/70">No runs match.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $runs->links() }}</div>
  </div>
</x-app-layout>
