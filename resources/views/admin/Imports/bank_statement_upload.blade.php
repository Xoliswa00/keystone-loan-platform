<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Bank Statement Import</span>
    <p class="kc-page-subtitle">Upload CSV bank statement for 3-way reconciliation</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Upload Statement</h4>
      <div class="p-3 rounded-lg border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/70 mb-4">
        <strong>Format:</strong> Standard SA bank CSV export · FNB, Nedbank, ABSA, Standard Bank formats are all supported.
        Columns detected automatically: Date, Description, Debit, Credit, Balance.
      </div>
      <form method="POST" action="{{ route('bank-statement.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
          <label class="kc-label">Bank Name</label>
          <select name="bank_name" class="kc-select" required>
            <option value="">Select bank...</option>
            @foreach(['FNB','Nedbank','ABSA','Standard Bank','Capitec','African Bank','TymeBank','Discovery Bank'] as $b)
              <option value="{{ $b }}">{{ $b }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="kc-label">Statement Period (YYYY-MM)</label>
          <input type="text" name="period" class="kc-input font-mono" placeholder="{{ now()->format('Y-m') }}" pattern="\d{4}-\d{2}" required>
        </div>
        <div>
          <label class="kc-label">CSV File</label>
          <input type="file" name="file" accept=".csv,.txt" class="kc-input py-2 cursor-pointer" required>
        </div>
        <button type="submit" class="kc-btn-primary w-full justify-center">Upload & Stage</button>
      </form>
    </div>

    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-4">Recent Imports</h4>
      @foreach($batches as $batch)
      @php $bsc = match($batch->status){'VALIDATED'=>'kc-badge-green','CAPTURED'=>'kc-badge-gold',default=>'kc-badge-silver'}; @endphp
      <div class="flex items-center justify-between p-3 rounded-lg border border-kc-silver-light mb-2">
        <div>
          <p class="text-sm font-semibold font-mono">{{ $batch->import_ref }}</p>
          <p class="text-xs text-kc-charcoal/60">{{ $batch->row_count }} rows · {{ $batch->created_at->format('d M Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="kc-badge {{ $bsc }}">{{ $batch->status }}</span>
          <a href="{{ route('bank-statement.show', $batch->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View</a>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</x-app-layout>
