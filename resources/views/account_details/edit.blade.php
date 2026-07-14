<x-app-layout>
    <div class="container">
        <h1>Edit Account Detail</h1>

        <form action="{{ route('accountdetails.update', $accountDetail) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id" class="form-control" required>
                    <option value="">Select User</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ $accountDetail->user_id == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('user_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="account_holder_name">Account Holder Name</label>
                <input type="text" name="account_holder_name" id="account_holder_name" class="form-control" value="{{ old('account_holder_name', $accountDetail->account_holder_name) }}" required>
                @error('account_holder_name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="bank_name">Bank Name</label>
                <input type="text" name="bank_name" id="bank_name" class="form-control" value="{{ old('bank_name', $accountDetail->bank_name) }}" required>
                @error('bank_name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="account_number">Account Number</label>
                <input type="text" name="account_number" id="account_number" class="form-control" value="{{ old('account_number', $accountDetail->account_number) }}" required>
                @error('account_number')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="account_type">Account Type</label>
                <select name="account_type" id="account_type" class="form-control" required>
                    <option value="savings" {{ old('account_type', $accountDetail->account_type) == 'savings' ? 'selected' : '' }}>Savings</option>
                    <option value="checking" {{ old('account_type', $accountDetail->account_type) == 'checking' ? 'selected' : '' }}>Cheque / Current</option>
                </select>
                @error('account_type')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" id="payment_method" class="form-control" required>
                    <option value="debit_order" {{ old('payment_method', $accountDetail->payment_method) == 'debit_order' ? 'selected' : '' }}>Debit Order</option>
                    <option value="manual" {{ old('payment_method', $accountDetail->payment_method) == 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
                @error('payment_method')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active" {{ old('status', $accountDetail->status) == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', $accountDetail->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('status')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Update Account Detail</button>
        </form>
    </div>
</x-app-layout>
