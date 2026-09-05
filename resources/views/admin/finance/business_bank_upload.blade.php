<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Business Bank Statement</span>
    <p class="kc-page-subtitle">Upload Keystone's own bank statement for 3-way reconciliation</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="kc-card">
      <h4 class="font-display font-semibold text-kc-navy mb-2">Upload Statement</h4>
      <div class="p-3 rounded-lg border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/70 mb-4">
        This is Keystone's <strong>own</strong> bank statement — not a customer's.
        The system will auto-reconcile against Nu-Pay batches, loan disbursements,
        and funding facility transactions.
        <br><br>
        <strong>Format:</strong> CSV export from your bank. FNB, Nedbank, ABSA, Standard Bank formats supported.
      </div>
      <form method="POST" action="{{ route('admin.finance.business-bank.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
          <label class="kc-label">Bank Name</label>
          <select name="bank_name" class="kc-select" required>
            <option value="">Select...</option>
            @foreach(['FNB','Nedbank','ABSA','Standard Bank','Capitec','African Bank'] as $b)
              <option>{{ $b }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="kc-label">Account Name</label>
          <input type="text" name="account_name" class="kc-input" placeholder="e.g. Keystone Capital Partners — Collections" required>
        </div>
        <div>
          <label class="kc-label">Account Number (last 4 digits)</label>
          <input type="text" name="account_number" class="kc-input font-mono" maxlength="4" placeholder="XXXX">
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
      <h4 class="font-display font-semibold text-kc-navy mb-4">Recent Statements</h4>
      @forelse($recentBatches as $batch)
      @php $meta = json_decode($batch->meta??'{}',true); $bsc=match($batch->status){'VALIDATED'=>'kc-badge-green','CAPTURED'=>'kc-badge-gold',default=>'kc-badge-silver'}; @endphp
      <div class="flex items-center justify-between p-3 rounded-lg border border-kc-silver-light mb-2">
        <div>
          <p class="text-sm font-semibold font-mono">{{ $batch->import_ref }}</p>
          <p class="text-xs text-kc-charcoal/70">{{ $meta['bank_name'] ?? '' }} · {{ $meta['period'] ?? '' }} · {{ $batch->row_count }} rows</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="kc-badge {{ $bsc }}">{{ $batch->status }}</span>
          <a href="{{ route('admin.finance.business-bank.show', $batch->id) }}" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">View</a>
        </div>
      </div>
      @empty
      <p class="text-sm text-kc-charcoal/70">No statements uploaded yet.</p>
      @endforelse
    </div>
  </div>
</x-app-layout>
