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
  <div class="flex justify-end mb-4">
    <form method="POST" action="{{ route('nu-pay.import.post', $batch->import_ref) }}">
      @csrf
      <button type="submit" class="kc-btn-primary"
        onclick="return confirm('Post all staged transactions to GL?')">
        Post to GL ({{ $transactions->count() }} transactions)
      </button>
    </form>
  </div>
  @endif

  {{-- Transactions --}}
  <div class="kc-card">
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Debtor ID</th><th>Name</th><th>Amount</th><th>Type</th><th>Action Date</th><th>Status</th><th>Posted</th>
        </tr></thead>
        <tbody>
          @forelse($transactions as $txn)
          @php $tc = str_contains(strtolower($txn->transaction_type??''),'success') ? 'kc-badge-green' : (str_contains(strtolower($txn->transaction_type??''),'fail') ? 'kc-badge-red' : 'kc-badge-silver'); @endphp
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
          </tr>
          @empty
          <tr><td colspan="7" class="text-center py-8 text-kc-charcoal/40">No transactions staged.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
