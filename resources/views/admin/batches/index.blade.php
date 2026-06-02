<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Import Batches</span>
    <p class="kc-page-subtitle">Nu-Pay transaction imports</p>
  </x-slot>

  <div class="flex justify-end mb-4">
    <a href="{{ route('nupay.upload.form') }}" class="kc-btn-primary">New Import</a>
  </div>

  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Ref</th><th>File</th><th>Rows</th><th>Status</th><th>Imported</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse($batches as $batch)
          @php $bsc = match($batch->status){'PROCESSED'=>'kc-badge-green','CAPTURED'=>'kc-badge-gold','FAILED_CAPTURE','FAILED_VALIDATION'=>'kc-badge-red',default=>'kc-badge-silver'}; @endphp
          <tr>
            <td data-label="Ref" class="font-mono text-xs">{{ $batch->import_ref }}</td>
            <td data-label="File" class="text-xs text-kc-charcoal/70">{{ $batch->original_filename }}</td>
            <td data-label="Rows" class="font-semibold">{{ $batch->row_count }}</td>
            <td data-label="Status"><span class="kc-badge {{ $bsc }}">{{ $batch->status }}</span></td>
            <td data-label="Imported" class="text-xs text-kc-charcoal/50">{{ $batch->created_at->format('d M Y H:i') }}</td>
            <td data-label="Actions">
              <a href="{{ route('nu-pay.import.show', $batch->import_ref) }}" class="text-xs text-kc-gold hover:underline">View</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center py-8 text-kc-charcoal/40">No imports yet. <a href="{{ route('nupay.upload.form') }}" class="text-kc-gold hover:underline">Upload now →</a></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>
  </div>
</x-app-layout>
