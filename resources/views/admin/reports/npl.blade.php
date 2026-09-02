<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">NPL Report — Non-Performing Loans</span>
    <p class="kc-page-subtitle">IFRS 9 staging by Days Past Due · As at {{ \Carbon\Carbon::parse($asAt)->format('d F Y') }}</p>
  </x-slot>

  <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div><label class="kc-label">As at date</label>
      <input type="date" name="as_at_date" value="{{ $asAt }}" class="kc-input"></div>
    <div class="flex items-end"><button type="submit" class="kc-btn-primary">Apply</button></div>
  </form>

  {{-- IFRS 9 Stage Summary --}}
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    @foreach($stageSummary as $stage)
    @php
      $colour = str_contains($stage->stage, 'Stage 3') ? 'border-red-400 bg-red-50'
              : (str_contains($stage->stage, 'Stage 2') ? 'border-orange-400 bg-orange-50'
              : 'border-kc-gold/40 bg-kc-gold/5');
    @endphp
    <div class="kc-card border-l-4 {{ $colour }}">
      <p class="text-xs font-semibold uppercase tracking-wider text-kc-charcoal/50">{{ $stage->stage }}</p>
      <p class="font-display text-xl font-semibold text-kc-navy mt-1">{{ $stage->loan_count }} loan(s)</p>
      <table class="w-full text-xs mt-2">
        <tr><td class="text-kc-charcoal/50">Outstanding</td><td class="text-right font-semibold">R {{ number_format($stage->total_outstanding, 2) }}</td></tr>
        <tr><td class="text-kc-charcoal/50">Arrears</td><td class="text-right font-semibold text-red-600">R {{ number_format($stage->total_arrears, 2) }}</td></tr>
        <tr class="border-t border-kc-silver-light"><td class="text-kc-charcoal/50">ECL Provision</td><td class="text-right font-semibold text-orange-600">R {{ number_format($stage->total_provision, 2) }}</td></tr>
      </table>
    </div>
    @endforeach
  </div>

  {{-- Loan detail --}}
  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">Overdue Loan Detail</h4>
      <a href="{{ route('admin.recovery.index') }}" class="text-xs text-kc-gold hover:text-kc-gold-muted">View Recovery Cases →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="kc-table">
        <thead><tr>
          <th>Loan</th><th>Client</th><th>DPD</th><th>Bucket</th><th>Stage</th>
          <th>Outstanding</th><th>Arrears</th><th>Provision</th><th>Coverage %</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($nplLoans as $loan)
          @php
            $coverage = $loan->remaining_balance > 0
                ? round(($loan->provision_amount / $loan->remaining_balance) * 100, 1) : 0;
            $stageClass = $loan->ifrs9_stage == 3 ? 'kc-badge-red'
                        : ($loan->ifrs9_stage == 2 ? 'kc-badge-gold' : 'kc-badge-silver');
          @endphp
          <tr>
            <td class="font-mono text-xs">#{{ str_pad($loan->loan_id, 6, '0', STR_PAD_LEFT) }}</td>
            <td>
              <div class="font-semibold text-kc-navy">{{ $loan->client_name }}</div>
              <div class="text-xs text-kc-charcoal/40">{{ $loan->customer_code }}</div>
            </td>
            <td class="font-bold {{ $loan->dpd >= 90 ? 'text-red-600' : ($loan->dpd >= 30 ? 'text-orange-500' : 'text-kc-charcoal') }}">
              {{ $loan->dpd }}
            </td>
            <td><span class="kc-badge {{ $stageClass }}">{{ $loan->dpd_bucket }}</span></td>
            <td><span class="kc-badge {{ $stageClass }}">Stage {{ $loan->ifrs9_stage }}</span></td>
            <td class="font-semibold">R {{ number_format($loan->remaining_balance, 2) }}</td>
            <td class="text-red-600 font-semibold">R {{ number_format($loan->arrears, 2) }}</td>
            <td class="text-orange-600">R {{ number_format($loan->provision_amount, 2) }}</td>
            <td>
              <div class="flex items-center gap-1">
                <div class="w-12 h-1.5 bg-kc-silver-light rounded-full overflow-hidden">
                  <div class="h-full bg-orange-500 rounded-full" style="width:{{ min(100, $coverage) }}%"></div>
                </div>
                <span class="text-xs">{{ $coverage }}%</span>
              </div>
            </td>
            <td>
              <a href="{{ route('admin.collections') }}" class="text-xs text-kc-gold hover:underline">Collections</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="10" class="text-center text-kc-charcoal/40 py-8">No overdue loans. 🎉</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $nplLoans->links() }}</div>
  </div>

  {{-- What happens to overdue loans explanation --}}
  <div class="kc-card mt-6">
    <h4 class="font-display font-semibold text-kc-navy mb-3">Bad Debt → Recovery Flow</h4>
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-2 text-xs text-center">
      @foreach([
        ['step'=>'Missed Payment','note'=>'schedule: payment_failed','colour'=>'bg-orange-100 border-orange-300'],
        ['step'=>'Provision','note'=>'IFRS 9 ECL monthly','colour'=>'bg-yellow-100 border-yellow-300'],
        ['step'=>'Collections','note'=>'Contact & escalation log','colour'=>'bg-blue-100 border-blue-300'],
        ['step'=>'180 DPD Write-off','note'=>'GL: Dr Allowance / Cr Loans Recv','colour'=>'bg-red-100 border-red-300'],
        ['step'=>'Recovery','note'=>'GL: Dr Bank / Cr Recovery Income','colour'=>'bg-emerald-100 border-emerald-300'],
      ] as $i => $s)
      <div>
        <div class="border rounded p-2 {{ $s['colour'] }} font-semibold">{{ $s['step'] }}</div>
        <div class="text-kc-charcoal/40 mt-1">{{ $s['note'] }}</div>
        @if($i < 4) <div class="text-kc-charcoal/30 text-base">→</div> @endif
      </div>
      @endforeach
    </div>
  </div>
</x-app-layout>
