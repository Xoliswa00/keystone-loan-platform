<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Preview — {{ $profile->name }}</span>
    <p class="kc-page-subtitle">Period {{ $period }} · first 20 rows · nothing transmitted</p>
  </x-slot>

  <div class="kc-card">
    <div class="flex items-center justify-between mb-3">
      <span class="kc-badge {{ \App\Models\ExportRun::STATUS_CLASSES[$run->status] ?? 'kc-badge-silver' }}">{{ $run->status }}</span>
      <a href="{{ route('admin.exports.profiles.edit', $profile) }}" class="text-xs text-kc-navy underline underline-offset-2">Back to profile</a>
    </div>

    @if($run->status === 'built' && count($rows))
      <p class="text-xs text-kc-charcoal/70 mb-2">{{ number_format($run->row_count) }} row(s) match. Disputed accounts are shown here but held back from real runs.</p>
      <div class="kc-table-scroll">
        <table class="kc-table text-xs">
          <tbody>
            @foreach($rows as $i => $line)
              <tr class="{{ $i === 0 && $profile->include_header ? 'font-semibold bg-kc-white' : '' }}">
                @foreach(str_getcsv($line, $profile->delimiter ?: ',', $profile->enclosure ?: '"', '') as $cell)
                  <td class="whitespace-nowrap">{{ $cell }}</td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-sm text-kc-charcoal/70">
        Nothing to preview{{ $run->error_message ? ' — '.$run->error_message : '' }}.
        @if($run->status === 'skipped') Build a snapshot for {{ $period }} first (<code>keystone:build-account-snapshots {{ $period }}</code>). @endif
      </p>
    @endif
  </div>
</x-app-layout>
