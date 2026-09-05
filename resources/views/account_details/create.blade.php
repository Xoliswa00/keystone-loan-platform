<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Add Bank Account</span>
    <p class="kc-page-subtitle">This account will be used for debit order collections</p>
  </x-slot>

  <div class="kc-card max-w-lg">
    <form action="{{ route('accountdetails.store') }}" method="POST" class="space-y-4"
      x-data="{
        branchCodes: {
          'ABSA': '632005',
          'African Bank': '430000',
          'Capitec Bank': '470010',
          'Discovery Bank': '679000',
          'FNB (First National Bank)': '250655',
          'Nedbank': '198765',
          'Standard Bank': '051001',
          'TymeBank': '678910',
          'Old Mutual': '462005',
          'Investec': '580105',
          'Bidvest Bank': '462005',
        },
        branchCode: '{{ old('branch_code') }}',
      }">
      @csrf
      <input type="hidden" name="status" value="active">
      <div>
        <label class="kc-label">Account Holder Name <span class="text-red-500">*</span></label>
        <input type="text" name="account_holder_name" value="{{ old('account_holder_name', Auth::user()->name) }}"
          class="kc-input @error('account_holder_name') border-red-400 @enderror" required>
        @error('account_holder_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="kc-label">Bank <span class="text-red-500">*</span></label>
        <select name="bank_name" class="kc-select" required
          @change="branchCode = branchCodes[$event.target.value] || ''">
          <option value="">Select your bank...</option>
          @foreach(['ABSA','African Bank','Capitec Bank','Discovery Bank','FNB (First National Bank)','Nedbank','Standard Bank','TymeBank','Old Mutual','Investec','Bidvest Bank'] as $bank)
            <option value="{{ $bank }}" {{ old('bank_name') === $bank ? 'selected' : '' }}>{{ $bank }}</option>
          @endforeach
        </select>
        @error('bank_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="kc-label">Account Number <span class="text-red-500">*</span></label>
        <input type="text" name="account_number" value="{{ old('account_number') }}"
          class="kc-input font-mono @error('account_number') border-red-400 @enderror" required>
        @error('account_number')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="kc-label">Branch Code</label>
        <input type="text" name="branch_code" x-model="branchCode" class="kc-input font-mono" placeholder="Filled in automatically once you pick a bank">
        <p class="mt-1 text-[11px] text-kc-charcoal/70">Every branch of a bank shares one universal code — filled in for you, but you can edit it if yours differs.</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="kc-label">Account Type <span class="text-red-500">*</span></label>
          <select name="account_type" class="kc-select" required>
            <option value="savings"  {{ old('account_type')==='savings'  ?'selected':'' }}>Savings</option>
            <option value="checking" {{ old('account_type')==='checking' ?'selected':'' }}>Cheque / Current</option>
          </select>
        </div>
        <div>
          <label class="kc-label">Payment Method</label>
          <select name="payment_method" class="kc-select">
            <option value="debit_order">Debit Order (Nu-Pay)</option>
          </select>
        </div>
      </div>

      <div class="p-3 rounded-lg border border-kc-gold/20 bg-kc-gold/5 text-xs text-kc-charcoal/70">
        By adding this account you authorise Keystone Capital Partners to debit it for loan repayments per your agreed schedule.
      </div>

      <div class="flex gap-3">
        <a href="{{ route('accountdetails.index') }}" class="kc-btn-ghost">Cancel</a>
        <button type="submit" class="kc-btn-primary flex-1 justify-center">Save Bank Account</button>
      </div>
    </form>
  </div>
</x-app-layout>
