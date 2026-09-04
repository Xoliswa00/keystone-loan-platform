<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Recovery Case — Loan #{{ str_pad($recovery->loan_id, 6, '0', STR_PAD_LEFT) }}</span>
    <p class="kc-page-subtitle">{{ $recovery->user?->name }} · Recovery Rate: {{ $recovery->recoveryRate() }}%</p>
  </x-slot>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Case overview --}}
    <div class="lg:col-span-2 space-y-6">

      {{-- Stats --}}
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="kc-stat-card">
          <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Written Off</span>
          <p class="font-display text-xl font-semibold text-red-600 mt-1">R {{ number_format($recovery->original_write_off_amount, 2) }}</p>
        </div>
        <div class="kc-stat-card">
          <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Recovered</span>
          <p class="font-display text-xl font-semibold text-emerald-600 mt-1">R {{ number_format($recovery->total_recovered, 2) }}</p>
        </div>
        <div class="kc-stat-card">
          <span class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/60">Still Owed</span>
          <p class="font-display text-xl font-semibold text-orange-600 mt-1">R {{ number_format($recovery->outstanding_recovery, 2) }}</p>
        </div>
      </div>

      {{-- Record payment --}}
      @if($recovery->status !== 'recovered' && $recovery->status !== 'closed')
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Record Recovery Payment</h4>
        <form method="POST" action="{{ route('admin.recovery.payment', $recovery) }}" class="grid grid-cols-2 gap-4">
          @csrf
          <div><label class="kc-label">Amount (max R{{ number_format($recovery->outstanding_recovery, 2) }})</label>
            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $recovery->outstanding_recovery }}" class="kc-input" required></div>
          <div><label class="kc-label">Payment Date</label>
            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="kc-input" required></div>
          <div><label class="kc-label">Method</label>
            <select name="payment_method" class="kc-select" required>
              <option value="eft">EFT</option>
              <option value="cash">Cash</option>
              <option value="nupay">Nu-Pay</option>
              <option value="legal_settlement">Legal Settlement</option>
            </select></div>
          <div><label class="kc-label">Reference</label>
            <input type="text" name="payment_reference" class="kc-input" placeholder="Optional"></div>
          <div class="col-span-2"><label class="kc-label">Notes</label>
            <textarea name="notes" rows="2" class="kc-input"></textarea></div>
          <div class="col-span-2 flex justify-end">
            <button type="submit" class="kc-btn-primary">Record Payment</button>
          </div>
        </form>
      </div>
      @endif

      {{-- Payment history --}}
      @if($recovery->payments->isNotEmpty())
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Payment History</h4>
        <table class="kc-table">
          <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Received By</th></tr></thead>
          <tbody>
            @foreach($recovery->payments as $p)
            <tr>
              <td data-label="Date">{{ $p->payment_date->format('d M Y') }}</td>
              <td data-label="Amount" class="font-semibold text-emerald-600">R {{ number_format($p->amount, 2) }}</td>
              <td data-label="Method">{{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}</td>
              <td data-label="Reference" class="text-xs font-mono">{{ $p->payment_reference ?? '—' }}</td>
              <td data-label="Received By" class="text-xs">{{ $p->receivedBy?->name ?? '—' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif

      {{-- Contact notes --}}
      @if($notes->isNotEmpty())
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Contact Log</h4>
        <div class="space-y-3">
          @foreach($notes as $note)
          <div class="border-l-2 border-kc-gold/40 pl-3">
            <p class="text-xs text-kc-charcoal/60">{{ \Carbon\Carbon::parse($note->created_at)->format('d M Y H:i') }}</p>
            <p class="text-sm">{{ $note->note }}</p>
          </div>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    {{-- Case management sidebar --}}
    <div class="space-y-4">
      <div class="kc-card">
        <h4 class="font-semibold text-kc-navy mb-4">Case Management</h4>
        <form method="POST" action="{{ route('admin.recovery.update', $recovery) }}" class="space-y-3">
          @csrf @method('PATCH')
          <div><label class="kc-label">Status</label>
            <select name="status" class="kc-select">
              @foreach(\App\Models\DebtRecovery::STATUSES as $val => $info)
                <option value="{{ $val }}" {{ $recovery->status === $val ? 'selected' : '' }}>{{ $info['label'] }}</option>
              @endforeach
            </select></div>
          <div><label class="kc-label">Assign To</label>
            <select name="assigned_to" class="kc-select">
              <option value="">Unassigned</option>
              @foreach(\App\Models\User::where('rule_id', 2)->orderBy('name')->get() as $u)
                <option value="{{ $u->id }}" {{ $recovery->assigned_to == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
              @endforeach
            </select></div>
          <div><label class="kc-label">Next Action Date</label>
            <input type="date" name="next_action_date" value="{{ $recovery->next_action_date?->toDateString() }}" class="kc-input"></div>
          <div><label class="kc-label">External Agency</label>
            <input type="text" name="external_agency" value="{{ $recovery->external_agency }}" class="kc-input" placeholder="Agency name"></div>
          <div><label class="kc-label">Legal Reference</label>
            <input type="text" name="legal_reference" value="{{ $recovery->legal_reference }}" class="kc-input" placeholder="Case / attorney ref"></div>
          <div><label class="kc-label">Notes</label>
            <textarea name="notes" rows="3" class="kc-input">{{ $recovery->notes }}</textarea></div>
          <button type="submit" class="kc-btn-secondary w-full justify-center">Update Case</button>
        </form>
      </div>
    </div>
  </div>
</x-app-layout>
