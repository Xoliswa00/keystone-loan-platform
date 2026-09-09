<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Data Exports</span>
    <p class="kc-page-subtitle">Outbound monthly feeds to credit bureaux and partners</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  @unless($enabled && $snapshotsEnabled)
    <div class="kc-alert-error mb-5">
      <p class="font-semibold">The export engine is not live.</p>
      <ul class="list-disc list-inside text-xs mt-1 space-y-0.5">
        @unless($snapshotsEnabled)<li><code>DATA_EXPORTS_SNAPSHOTS_ENABLED</code> is off — no monthly snapshot is built.</li>@endunless
        @unless($enabled)<li><code>DATA_EXPORTS_ENABLED</code> is off — profiles are configurable but nothing is sent.</li>@endunless
        <li>Both must be added to the POPIA records-of-processing before being switched on in production.</li>
      </ul>
    </div>
  @endunless

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
      ['Active recipients', $activeRecipients, route('admin.exports.recipients.index')],
      ['Active profiles', $activeProfiles, route('admin.exports.profiles.index')],
      ['Open disputes', $openDisputes, route('admin.exports.disputes.index')],
      ['Recent runs', $recentRuns->count(), route('admin.exports.runs.index')],
    ] as [$label, $value, $href])
      <a href="{{ $href }}" class="kc-card hover:shadow-kc-card transition-shadow">
        <p class="text-xs text-kc-charcoal/70">{{ $label }}</p>
        <p class="text-2xl font-display font-semibold text-kc-navy mt-1">{{ $value }}</p>
      </a>
    @endforeach
  </div>

  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">Latest runs</h4>
      <a href="{{ route('admin.exports.runs.index') }}" class="text-xs text-kc-navy underline underline-offset-2">All runs</a>
    </div>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>When</th><th>Profile</th><th>Recipient</th><th>Period</th><th>Rows</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($recentRuns as $run)
            <tr>
              <td data-label="When" class="text-xs">{{ $run->created_at->diffForHumans() }}</td>
              <td data-label="Profile" class="text-xs">
                <a href="{{ route('admin.exports.runs.show', $run) }}" class="text-kc-navy underline underline-offset-2">{{ $run->profile?->name ?? '—' }}</a>
              </td>
              <td data-label="Recipient" class="text-xs">{{ $run->profile?->recipient?->name ?? '—' }}</td>
              <td data-label="Period" class="text-xs font-mono">{{ $run->period }}</td>
              <td data-label="Rows" class="text-xs">{{ number_format($run->row_count) }}</td>
              <td data-label="Status"><span class="kc-badge {{ \App\Models\ExportRun::STATUS_CLASSES[$run->status] ?? 'kc-badge-silver' }}">{{ $run->status }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center py-8 text-kc-charcoal/70">No export runs yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
