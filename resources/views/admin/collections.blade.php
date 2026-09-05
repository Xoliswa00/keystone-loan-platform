<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Collections Queue</span>
    <p class="kc-page-subtitle">Overdue accounts requiring contact</p>
  </x-slot>

  <div class="kc-card">
    <table class="kc-table">
      <thead><tr>
        <th>Client</th><th>DPD</th><th>Missed</th><th>Arrears</th><th>Balance</th><th>Last Contact</th><th>Actions</th>
      </tr></thead>
      <tbody>
        @forelse($overdue as $row)
        <tr>
          <td data-label="Client">
            <div class="font-semibold text-kc-navy">{{ $row->client_name }}</div>
            <div class="text-xs text-kc-charcoal/70">{{ $row->customer_code }} · {{ $row->phone }}</div>
          </td>
          <td data-label="DPD">
            @php $cls = $row->days_overdue >= 90 ? 'kc-badge-red' : ($row->days_overdue >= 60 ? 'kc-badge-gold' : 'kc-badge-silver'); @endphp
            <span class="kc-badge {{ $cls }}">{{ $row->days_overdue }} DPD</span>
          </td>
          <td data-label="Missed">{{ $row->instalments_missed }}</td>
          <td data-label="Arrears" class="font-semibold text-red-600">R {{ number_format($row->arrears_total, 2) }}</td>
          <td data-label="Balance">R {{ number_format($row->remaining_balance, 2) }}</td>
          <td data-label="Last Contact" class="text-xs text-kc-charcoal/70">
            @if($row->last_contact_date)
              {{ \Carbon\Carbon::parse($row->last_contact_date)->diffForHumans() }}<br>
              <span class="truncate max-w-xs block">{{ Str::limit($row->last_contact_note, 60) }}</span>
            @else
              <span class="text-red-500">No contact yet</span>
            @endif
          </td>
          <td data-label="Actions">
            <div x-data="{ open: false }" class="relative">
              <button @click="open = !open" class="kc-btn-ghost text-xs">Log Contact</button>
              <div x-show="open" @click.outside="open = false"
                class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-kc-card border border-kc-silver-light p-4 z-50">
                <form method="POST" action="{{ route('admin.collections.log') }}" class="space-y-3">
                  @csrf
                  <input type="hidden" name="loan_application_id" value="{{ $row->loan_application_id }}">
                  <div>
                    <label class="kc-label">Method</label>
                    <select name="contact_method" class="kc-select text-xs">
                      <option value="call">Call</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="email">Email</option>
                      <option value="sms">SMS</option>
                    </select>
                  </div>
                  <div>
                    <label class="kc-label">Outcome</label>
                    <select name="outcome" class="kc-select text-xs">
                      <option value="reached">Reached — promised payment</option>
                      <option value="no_answer">No answer</option>
                      <option value="promised_payment">Promised payment date</option>
                      <option value="disputed">Disputed amount</option>
                      <option value="legal_referral">Refer to legal</option>
                    </select>
                  </div>
                  <div>
                    <label class="kc-label">Note</label>
                    <textarea name="note" rows="2" class="kc-input text-xs"></textarea>
                  </div>
                  <button type="submit" class="kc-btn-primary w-full justify-center text-xs">Save</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-kc-charcoal/70 py-10">No overdue accounts. 🎉</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="mt-4">{{ $overdue->links() }}</div>
  </div>
</x-app-layout>
