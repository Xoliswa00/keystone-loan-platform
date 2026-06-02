<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Bank Accounts</span>
    <p class="kc-page-subtitle">Accounts registered for debit order collections</p>
  </x-slot>

  <div class="flex justify-end mb-4">
    <a href="{{ route('accountdetails.create') }}" class="kc-btn-primary">+ Add Bank Account</a>
  </div>

  <div class="kc-card">
    @if($accountDetails->isEmpty())
    <div class="text-center py-10">
      <p class="text-kc-charcoal/50 mb-4">No bank accounts on file. Add one to enable debit order repayments.</p>
      <a href="{{ route('accountdetails.create') }}" class="kc-btn-primary">Add Bank Account</a>
    </div>
    @else
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr>
          <th>Account Holder</th><th>Bank</th><th>Account Number</th><th>Type</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          @foreach($accountDetails as $acct)
          <tr>
            <td data-label="Holder" class="font-semibold">{{ $acct->account_holder_name }}</td>
            <td data-label="Bank">{{ $acct->bank_name }}</td>
            <td data-label="Number" class="font-mono text-sm">****{{ substr($acct->account_number, -4) }}</td>
            <td data-label="Type"><span class="kc-badge kc-badge-silver">{{ ucfirst($acct->account_type) }}</span></td>
            <td data-label="Status">
              <span class="kc-badge {{ $acct->status === 'active' ? 'kc-badge-green' : 'kc-badge-silver' }}">{{ ucfirst($acct->status) }}</span>
            </td>
            <td data-label="Actions">
              <div class="flex gap-3">
                <a href="{{ route('accountdetails.edit', $acct) }}" class="text-xs text-kc-gold hover:underline">Edit</a>
                <form method="POST" action="{{ route('accountdetails.destroy', $acct) }}" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="text-xs text-red-500 hover:underline"
                    onclick="return confirm('Remove this bank account?')">Remove</button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <div class="mt-4 p-4 rounded-xl border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/60">
    <strong>Important:</strong> Ensure your account has sufficient funds before your instalment due date. Dishonoured debit orders incur a fee and may affect your credit record.
  </div>
</x-app-layout>
