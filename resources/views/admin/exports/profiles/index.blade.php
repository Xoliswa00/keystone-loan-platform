<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Export Profiles</span>
    <p class="kc-page-subtitle">Which columns each recipient gets, in what order, and when</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">All profiles</h4>
      <a href="{{ route('admin.exports.profiles.create') }}" class="kc-btn-primary text-sm">New Profile</a>
    </div>

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr><th>Name</th><th>Recipient</th><th>Dataset</th><th>Columns</th><th>Schedule</th><th>Runs</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($profiles as $p)
            <tr>
              <td data-label="Name" class="font-semibold">{{ $p->name }}</td>
              <td data-label="Recipient" class="text-xs">{{ $p->recipient?->name ?? '—' }}</td>
              <td data-label="Dataset" class="text-xs font-mono">{{ $p->dataset }}</td>
              <td data-label="Columns" class="text-xs">{{ count($p->columns ?? []) }}</td>
              <td data-label="Schedule" class="text-xs">day {{ $p->schedule_day_of_month }}, −{{ $p->period_offset }}mo</td>
              <td data-label="Runs" class="text-xs">{{ $p->runs_count }}</td>
              <td data-label="Status"><span class="kc-badge {{ $p->active ? 'kc-badge-green' : 'kc-badge-silver' }}">{{ $p->active ? 'Active' : 'Inactive' }}</span></td>
              <td data-label="Actions">
                <div class="flex items-center gap-2">
                  <a href="{{ route('admin.exports.profiles.edit', $p) }}" class="text-xs text-kc-navy underline underline-offset-2">Edit</a>
                  <form method="POST" action="{{ route('admin.exports.profiles.preview', $p) }}">
                    @csrf
                    <button type="submit" class="text-xs text-kc-navy underline underline-offset-2">Preview</button>
                  </form>
                  <form method="POST" action="{{ route('admin.exports.profiles.toggle', $p) }}">
                    @csrf
                    <button type="submit" class="kc-btn-ghost text-[10px] py-1 px-2">{{ $p->active ? 'Deactivate' : 'Activate' }}</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/70">No profiles configured yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
