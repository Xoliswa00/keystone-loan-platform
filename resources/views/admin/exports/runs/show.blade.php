<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Run #{{ $run->id }}</span>
    <p class="kc-page-subtitle">{{ $run->profile?->name ?? '—' }} · {{ $run->period }}</p>
  </x-slot>

  @if(session('success'))<div class="kc-alert-success mb-5">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="kc-alert-error mb-5">{{ session('error') }}</div>@endif

  <div class="kc-card max-w-3xl space-y-4">
    <div class="flex items-center gap-3">
      <span class="kc-badge {{ \App\Models\ExportRun::STATUS_CLASSES[$run->status] ?? 'kc-badge-silver' }}">{{ $run->status }}</span>
      <span class="text-xs text-kc-charcoal/70">{{ $run->created_at->format('Y-m-d H:i:s') }}</span>
    </div>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
      <dt class="text-kc-charcoal/60">Recipient</dt><dd>{{ $run->profile?->recipient?->name ?? '—' }}</dd>
      <dt class="text-kc-charcoal/60">Transport</dt><dd>{{ $run->transport }}</dd>
      <dt class="text-kc-charcoal/60">Triggered by</dt><dd>{{ $run->triggered_by }}</dd>
      <dt class="text-kc-charcoal/60">Snapshot generation</dt><dd>{{ $run->snapshot_generation ?? '—' }}</dd>
      <dt class="text-kc-charcoal/60">Rows</dt><dd>{{ number_format($run->row_count) }}</dd>
      <dt class="text-kc-charcoal/60">File size</dt><dd>{{ $run->file_size ? number_format($run->file_size).' bytes' : '—' }}</dd>
      <dt class="text-kc-charcoal/60">SHA-256</dt><dd class="font-mono break-all">{{ $run->file_sha256 ?? '—' }}</dd>
      <dt class="text-kc-charcoal/60">Started</dt><dd>{{ optional($run->started_at)->format('Y-m-d H:i:s') ?? '—' }}</dd>
      <dt class="text-kc-charcoal/60">Finished</dt><dd>{{ optional($run->finished_at)->format('Y-m-d H:i:s') ?? '—' }}</dd>
    </dl>

    @if($run->error_message)
      <div class="kc-alert-error text-xs">
        <p class="font-semibold">{{ $run->error_context['exception'] ?? 'Error' }}</p>
        <p>{{ $run->error_message }}</p>
      </div>
    @endif

    @if($run->transport_result)
      <div>
        <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">Transport result</p>
        <pre class="text-[11px] bg-kc-white border border-kc-silver rounded p-2 overflow-x-auto">{{ json_encode($run->transport_result, JSON_PRETTY_PRINT) }}</pre>
      </div>
    @endif

    @if($run->profile_snapshot)
      <div>
        <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">Effective config at run time</p>
        <pre class="text-[11px] bg-kc-white border border-kc-silver rounded p-2 overflow-x-auto">{{ json_encode($run->profile_snapshot, JSON_PRETTY_PRINT) }}</pre>
      </div>
    @endif

    <div class="flex gap-3 pt-2">
      @if($run->hasFile())
        <a href="{{ route('admin.exports.runs.download', $run) }}" class="kc-btn-primary text-sm">Download file</a>
      @else
        <span class="text-xs text-kc-charcoal/60">File no longer available.</span>
      @endif
      <form method="POST" action="{{ route('admin.exports.runs.rerun', $run) }}">
        @csrf
        <button type="submit" class="kc-btn-ghost text-sm"
          onclick="return confirm(@js('Re-run '.$run->profile?->name.' for '.$run->period.'? This sends again.'))">
          Re-run this period
        </button>
      </form>
      <a href="{{ route('admin.exports.runs.index') }}" class="kc-btn-ghost text-sm">Back</a>
    </div>
  </div>
</x-app-layout>
