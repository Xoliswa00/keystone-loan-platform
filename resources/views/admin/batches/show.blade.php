<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Import Batch: {{ $batch->import_ref }}</span>
    <p class="kc-page-subtitle">{{ $batch->original_filename }} · {{ $batch->row_count }} rows · {{ $batch->status }}</p>
  </x-slot>

  {{-- Summary by type --}}
  @isset($summary)
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    @foreach($summary as $month => $types)
    <div class="kc-card">
      <h5 class="font-semibold text-kc-navy mb-3">{{ $month }}</h5>
      @foreach($types as $type => $data)
      <div class="flex items-center justify-between py-1.5 border-b border-kc-silver-light/60 last:border-0">
        <div>
          <span class="kc-badge {{ str_contains(strtolower($type),'success') ? 'kc-badge-green' : (str_contains(strtolower($type),'fail') ? 'kc-badge-red' : 'kc-badge-silver') }}">
            {{ $type }}
          </span>
          <span class="text-xs text-kc-charcoal/50 ml-1">{{ $data['count'] }} txn</span>
        </div>
        <span class="text-sm font-semibold">R {{ number_format($data['total_amount'],2) }}</span>
      </div>
      @endforeach
    </div>
    @endforeach
  </div>
  @endisset

  {{-- Post button --}}
  @if($batch->status === 'CAPTURED')
  <div class="flex justify-end items-center gap-3 mb-4">
    @if($trackingPendingCount > 0)
    <span class="text-xs text-kc-charcoal/50">{{ $trackingPendingCount }} row(s) still 'tracking' — not yet resolved, won't be posted.</span>
    @endif
    @if($postableCount > 0)
    <form method="POST" action="{{ route('nu-pay.import.post', $batch->import_ref) }}">
      @csrf
      <button type="submit" class="kc-btn-primary"
        onclick="return confirm('Post all staged transactions to GL?')">
        Post to GL ({{ $postableCount }} transaction{{ $postableCount === 1 ? '' : 's' }})
      </button>
    </form>
    @endif
  </div>
  @endif

  {{-- Transactions --}}
  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Debtor ID</th><th>Name</th><th>Amount</th><th>Type</th><th>Action Date</th><th>Status</th><th>Posted</th><th>Actions</th>
        </tr></thead>
        @forelse($transactions as $txn)
        @php $tc = str_contains(strtolower($txn->transaction_type??''),'success') ? 'kc-badge-green' : (str_contains(strtolower($txn->transaction_type??''),'fail') ? 'kc-badge-red' : 'kc-badge-silver'); @endphp
        {{-- One <tbody> per row so the edit-toggle row below can share Alpine
             scope with its trigger row — sibling <tr>s can't share x-data,
             but multiple <tbody> elements are valid direct children of
             <table>, so this keeps the table semantically correct. --}}
        <tbody x-data="{open:false}">
          <tr>
            <td data-label="Debtor ID" class="font-mono text-xs">{{ $txn->debtor_id }}</td>
            <td data-label="Name" class="text-sm">{{ $txn->debtor_name }}</td>
            <td data-label="Amount" class="font-semibold">R {{ number_format($txn->instalment_amount,2) }}</td>
            <td data-label="Type"><span class="kc-badge {{ $tc }}">{{ $txn->transaction_type }}</span></td>
            <td data-label="Date" class="text-xs">{{ $txn->action_date }}</td>
            <td data-label="Status" class="text-xs">{{ $txn->status }}</td>
            <td data-label="Posted">
              @if($txn->posted_at)
                <span class="kc-badge kc-badge-green text-[10px]">Posted</span>
              @else
                <span class="kc-badge kc-badge-silver text-[10px]">Pending</span>
              @endif
            </td>
            <td data-label="Actions">
              @if(!$txn->posted_at)
              <button type="button" @click="open=!open" class="text-xs text-kc-gold hover:underline" x-text="open ? 'Cancel' : 'Edit'"></button>
              @else
              <span class="text-xs text-kc-charcoal/60">—</span>
              @endif
            </td>
          </tr>
          @if(!$txn->posted_at)
          <tr x-show="open" x-cloak>
            <td colspan="8" class="bg-kc-silver-light/40 p-3">
              <form method="POST" action="{{ route('nu-pay.import.transactions.update', [$batch->import_ref, $txn->id]) }}" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @csrf
                @method('PUT')
                <div>
                  <label class="kc-label text-[10px]">Debtor ID</label>
                  <input type="text" name="debtor_id" value="{{ $txn->debtor_id }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Debtor Name</label>
                  <input type="text" name="debtor_name" value="{{ $txn->debtor_name }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Mandate ID</label>
                  <input type="text" name="mandate_id" value="{{ $txn->mandate_id }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Instalment Amount</label>
                  <input type="number" step="0.01" name="instalment_amount" value="{{ $txn->instalment_amount }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Action Date</label>
                  <input type="date" name="action_date" value="{{ \Carbon\Carbon::parse($txn->action_date)->format('Y-m-d') }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Transaction Type</label>
                  <select name="transaction_type" class="kc-select text-sm">
                    @foreach(['success','failed','canceled','reversed','tracking'] as $t)
                      <option value="{{ $t }}" {{ $txn->transaction_type === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="kc-label text-[10px]">Mandate Request Tran ID</label>
                  <input type="text" name="mandate_request_tran_id" value="{{ $txn->mandate_request_tran_id }}" class="kc-input text-sm">
                </div>
                <div>
                  <label class="kc-label text-[10px]">Contract Reference</label>
                  <input type="text" name="contract_reference" value="{{ $txn->contract_reference }}" class="kc-input text-sm">
                </div>
                <div class="col-span-2 sm:col-span-4 flex justify-end gap-2">
                  <button type="button" @click="open=false" class="kc-btn-ghost text-xs">Cancel</button>
                  <button type="submit" class="kc-btn-primary text-xs">Save</button>
                </div>
              </form>
            </td>
          </tr>
          @endif
        </tbody>
        @empty
        <tbody>
          <tr><td colspan="8" class="text-center py-8 text-kc-charcoal/60">No transactions staged.</td></tr>
        </tbody>
        @endforelse
      </table>
    </div>
  </div>
</x-app-layout>
