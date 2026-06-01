<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-dark-200 leading-tight">
            {{ __('Loan Applications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container mx-auto mt-8">
                        <h1 class="text-3xl font-semibold">Create Loan Application</h1>

                        <form action="{{ route('loanapplications.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <!-- Loan Type -->
                            <div class="form-group mt-4">
                                <label for="loan_type" class="block text-white-700">Loan Type: (Personal or Business)</label>
                                <input type="text" name="loan_type" id="loan_type" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" value="{{ old('loan_type') }}" required>
                                @error('loan_type')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Loan Amount -->
                            <div class="form-group mt-4">
                                <label for="loan_amount" class="block text-white-700">Loan Amount:</label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" value="{{ old('loan_amount') }}" min="1" required>
                                @error('loan_amount')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Purpose -->
                            <div class="form-group mt-4">
                                <label for="purpose" class="block text-white-700">Purpose:</label>
                                <select name="purpose" id="purpose" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">-- Select Purpose --</option>
                                    <option value="Business" {{ old('purpose') == 'Business' ? 'selected' : '' }}>Business</option>
                                    <option value="Education" {{ old('purpose') == 'Education' ? 'selected' : '' }}>Education</option>
                                    <option value="Medical" {{ old('purpose') == 'Medical' ? 'selected' : '' }}>Medical</option>
                                    <option value="Other" {{ old('purpose') == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('purpose')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Other Purpose Input -->
                            <div class="form-group mt-4" id="other-purpose-group" style="display: none;">
                                <label for="other_purpose" class="block text-white-700">Specify Purpose:</label>
                                <input type="text" name="other_purpose" id="other_purpose" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" value="{{ old('other_purpose') }}">
                                @error('other_purpose')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Collateral -->
                            <div class="form-group mt-4">
                                <label for="collateral" class="block text-white-700">Collateral:</label>
                                <input type="text" name="collateral" id="collateral" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" value="{{ old('collateral') }}">
                                @error('collateral')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- File Uploads 
                            <div class="form-group mt-4">
                                <label for="credit_score_report" class="block text-white-700">Credit Score Report:</label>
                                <input type="file" name="credit_score_report" id="credit_score_report" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                @error('credit_score_report')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>-->

                            <div class="form-group mt-4">
                                <label for="bank_statement" class="block text-white-700">Bank Statement:</label>
                                <input type="file" name="bank_statement" id="bank_statement" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                @error('bank_statement')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group mt-4">
                                <label for="payslips" class="block text-white-700">Payslips:</label>
                                <input type="file" name="payslips" id="payslips" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                @error('payslips')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Fee Summary Preview (Dynamically Calculated) -->
                            <div class="form-group mt-6">
                                <h3 class="text-lg font-semibold text-white">Estimated Fees & Repayment</h3>
                                <ul class="text-sm text-white mt-2" id="fee-preview">
                                    <li><strong>Interest Rate:</strong> <span id="interestRate"></span>%</li>
                                    <li><strong>Initiation Fee:</strong> R<span id="initiationFee"></span></li>
                                    <li><strong>Service Fee:</strong> R<span id="serviceFee"></span></li>
                                    <li><strong>Total Repayment:</strong> R<span id="totalRepayment"></span></li>
                                </ul>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="form-group mt-4">
                                <div class="form-check">
                                    <input type="checkbox" name="terms" id="terms" class="form-check-input" required>
                                    <label for="terms" class="form-check-label text-white-700">
                                        I agree to the <a href="#" target="_blank" class="text-blue-500">Terms and Conditions</a>
                                    </label>
                                </div>
                                @error('terms')
                                    <small class="text-red-500">{{ $message }}</small>
                                @enderror
                            </div>

                            <x-primary-button>
                                {{ __('Submit Application') }}
                            </x-primary-button>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loanAmountInput = document.getElementById('loan_amount');
        const interestRateSpan = document.getElementById('interestRate');
        const initiationFeeSpan = document.getElementById('initiationFee');
        const serviceFeeSpan = document.getElementById('serviceFee');
        const totalRepaymentSpan = document.getElementById('totalRepayment');

        const baseInitiation = {{ $feeRules['base_initiation'] ?? 165 }};
        const initiationRate = {{ $feeRules['initiation_rate'] ?? 0.10 }};
        const maxInitiation = {{ $feeRules['max_initiation'] ?? 1050 }};
        const serviceFee = {{ $feeRules['service_fee'] ?? 60 }};
        const loyaltyStartDate = new Date("{{ $loyaltyStartDate }}");

        function updateFees() {
            const loanAmount = parseFloat(loanAmountInput.value);
            if (isNaN(loanAmount) || loanAmount <= 0) return;

            const today = new Date();
            const monthsSinceLoyalty = loyaltyStartDate ? ((today.getFullYear() - loyaltyStartDate.getFullYear()) * 12 + (today.getMonth() - loyaltyStartDate.getMonth())) : 0;

            const interestRate = monthsSinceLoyalty >= 12 ? 3 : 5;
            const interest = (loanAmount * interestRate) / 100;

            const excess = loanAmount > 1000 ? loanAmount - 1000 : 0;
            let initiation = baseInitiation + (excess * initiationRate);
            initiation = Math.min(initiation, maxInitiation);

            const total = loanAmount + interest + initiation + serviceFee;

            interestRateSpan.innerText = interestRate.toFixed(2);
            initiationFeeSpan.innerText = initiation.toFixed(2);
            serviceFeeSpan.innerText = serviceFee.toFixed(2);
            totalRepaymentSpan.innerText = total.toFixed(2);
        }

        loanAmountInput.addEventListener('input', updateFees);
        updateFees();
    });
</script>
@endpush
