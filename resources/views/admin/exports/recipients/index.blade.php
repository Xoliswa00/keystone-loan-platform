<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Export Recipients</span>
    <p class="kc-page-subtitle">Organisations that receive an outbound data feed</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  @php $canCreate = Auth::user()->hasRole('admin'); @endphp

  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">All recipients</h4>
      @if($canCreate)
        <a href="{{ route('admin.exports.recipients.create') }}" class="kc-btn-primary text-sm">New Recipient</a>
      @endif
    </div>

    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead>
          <tr><th>Name</th><th>Slug</th><th>Kind</th><th>Transport</th><th>Profiles</th><th>Lawful basis</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($recipients as $r)
            <tr>
              <td data-label="Name" class="font-semibold">{{ $r->name }}</td>
              <td data-label="Slug" class="text-xs font-mono text-kc-charcoal/70">{{ $r->slug }}</td>
              <td data-label="Kind"><span class="kc-badge {{ \App\Models\ExportRecipient::STATUS_CLASSES[$r->kind] ?? 'kc-badge-silver' }}">{{ \App\Models\ExportRecipient::KINDS[$r->kind] ?? $r->kind }}</span></td>
              <td data-label="Transport" class="text-xs">{{ \App\Models\ExportRecipient::TRANSPORTS[$r->transport] ?? $r->transport }}</td>
              <td data-label="Profiles" class="text-xs">{{ $r->profiles_count }}</td>
              <td data-label="Lawful basis" class="text-xs">{{ $r->lawful_basis ? 'Recorded' : '—' }}</td>
              <td data-label="Status"><span class="kc-badge {{ $r->active ? 'kc-badge-green' : 'kc-badge-silver' }}">{{ $r->active ? 'Active' : 'Inactive' }}</span></td>
              <td data-label="Actions">
                <div class="flex items-center gap-2">
                  <a href="{{ route('admin.exports.recipients.edit', $r) }}" class="text-xs text-kc-navy underline underline-offset-2">Edit</a>
                  <form method="POST" action="{{ route('admin.exports.recipients.toggle', $r) }}">
                    @csrf
                    <button type="submit" class="kc-btn-ghost text-[10px] py-1 px-2"
                      onclick="return confirm(@js(($r->active ? 'Deactivate' : 'Activate').' '.$r->name.'?'))">
                      {{ $r->active ? 'Deactivate' : 'Activate' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/70">No recipients configured yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
