<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Nu-Pay Import</span>
    <p class="kc-page-subtitle">Upload CSV or Excel — replaces the manual SQL import process</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Upload Nu-Pay File</h4>
      <div class="p-3 rounded-lg border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/70 mb-4">
        <strong>Accepted:</strong> .csv/.txt (single transaction type per file) or the full .xlsx/.xls
        Nu-Pay export (Success/Failed/Tracking/Reversed tabs, read automatically)
        · Duplicate transactions are automatically skipped.
      </div>
      <form method="POST" action="{{ route('nupay.upload') }}" enctype="multipart/form-data" class="space-y-4"
        x-data="{ ext: '' }">
        @csrf
        <div>
          <label class="kc-label">Select File</label>
          <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" class="kc-input py-2 cursor-pointer" required
            @change="ext = $event.target.value.split('.').pop().toLowerCase()">
          <p class="text-xs text-kc-charcoal/40 mt-1">Max 20 MB</p>
        </div>
        <div x-show="ext === 'csv' || ext === 'txt'" x-cloak>
          <label class="kc-label">Transaction Type</label>
          <select name="transaction_type" class="kc-select">
            <option value="">Select...</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="tracking">Tracking</option>
            <option value="reversed">Reversed</option>
          </select>
          <p class="text-xs text-kc-charcoal/40 mt-1">A CSV/TXT file has no tab to identify its type from — required for these formats only.</p>
        </div>
        <button type="submit" class="kc-btn-primary w-full justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
          </svg>
          Upload & Stage
        </button>
      </form>
    </div>

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Recent Imports</h4>
      @isset($recentBatches)
        @forelse($recentBatches as $batch)
        @php $bsc = match($batch->status){'PROCESSED'=>'kc-badge-green','CAPTURED'=>'kc-badge-gold','FAILED_CAPTURE','FAILED_VALIDATION'=>'kc-badge-red',default=>'kc-badge-silver'}; @endphp
        <div class="flex items-center justify-between p-3 rounded-lg border border-kc-silver-light hover:border-kc-gold/30 transition mb-2">
          <div>
            <p class="text-sm font-semibold font-mono text-kc-navy">{{ $batch->import_ref }}</p>
            <p class="text-xs text-kc-charcoal/50">{{ $batch->row_count }} rows · {{ $batch->created_at->format('d M Y H:i') }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="kc-badge {{ $bsc }}">{{ $batch->status }}</span>
            <a href="{{ route('nu-pay.import.show', $batch->import_ref) }}" class="text-xs text-kc-gold hover:underline">View</a>
          </div>
        </div>
        @empty
        <p class="text-sm text-kc-charcoal/40">No imports yet.</p>
        @endforelse
      @endisset
    </div>
  </div>
</x-app-layout>
