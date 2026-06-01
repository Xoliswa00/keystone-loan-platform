<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white-800 dark:text-white-200 leading-tight">
            {{ __('Loan Applications') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-blue-100 dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-white">
                    <div class="container mx-auto mt-8">
                        <h1 class="text-3xl font-semibold">Create Loan Application</h1>

                     <!--   <div class="mb-4">
                            <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                Loyalty Status: {{ ucfirst($loyaltyStatus) }}
                            </span>
                        </div>-->

                        <form action="{{ route('loanapplications.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="form-group mt-4">
                                <label for="loan_type" class="block text-white-700">Loan Type:</label>
                                <select name="loan_type" id="loan_type" class="form-control text-black block w-full mt-1 border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">-- Select Loan Type --</option>
                                    <option value="personal">Personal</option>
                                    <option value="home">Home</option>
                                    <option value="business">Business</option>
                                </select>
                            </div>

                            <div class="form-group mt-4">
                                <label for="purpose" class="block text-white-700">Purpose:</label>
                                <select name="purpose" id="purpose" class="form-control block w-full mt-1 border-gray-300 text-black rounded-md shadow-sm" onchange="handlePurposeChange(this)" required>
                                    <option value="">-- Select Purpose --</option>
                                    <option value="Debt Consolidation">Debt Consolidation</option>
                                    <option value="Emergency Medical Expenses">Emergency Medical Expenses</option>
                                    <option value="Home Repairs">Home Repairs</option>
                                    <option value="Car Repairs">Car Repairs</option>
                                    <option value="Education Expenses">Education Expenses</option>
                                    <option value="Business Needs">Business Needs</option>
                                    <option value="Moving or Relocation">Moving or Relocation</option>
                                    <option value="Unexpected Bills">Unexpected Bills</option>
                                    <option value="Travel or Vacation">Travel or Vacation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div id="other_purpose_div" class="form-group mt-4 hidden">
                                <label for="other_purpose" class="block text-white-700">Specify Other Purpose:</label>
                                <input type="text" id="other_purpose" name="other_purpose" class="form-control block rw-full mt-1 border-gray-300 text-blackrounded-md shadow-sm">
                            </div>

                          <!--  <div class="form-group mt-4">
                                <label for="collateral" class="block text-white-700">Collateral:</label>
                                <input type="text" name="collateral" id="collateral" class="form-control block w-full mt-1 text-black border-gray-300 rounded-md shadow-sm">
                            </div> -->

                         <div class="form-group mt-4">
    <label for="loan_amount" class="block text-white-700">Loan Amount:</label>
    <input 
        type="number" 
        name="loan_amount" 
        id="loan_amount" 
        class="form-control block w-full mt-1 text-black border-gray-300 rounded-md shadow-sm" 
        value="{{ old('loan_amount') }}" 
        required
        min="500" 
        max="3000"
        step="1"
        oninput="validateLoanAmount(this)"
    >
    <p id="loan_amount_error" class="text-red-600 text-sm mt-1 hidden">Loan amount must be between R500 and R3000.</p>
</div>

<script>
function validateLoanAmount(input) {
    const errorEl = document.getElementById('loan_amount_error');
    const value = parseFloat(input.value);
    if (value < 500 || value > 3000) {
        errorEl.classList.remove('hidden');
    } else {
        errorEl.classList.add('hidden');
    }
}
</script>

                            <div class="form-group mt-4">
                                <label for="months" class="block text-white-700">Repayment Period (Months):</label>
                                <select name="months" id="months" class="form-control block w-full mt-1 text-black border-gray-300 rounded-md shadow-sm" required>
                                    <option value="">-- Select Months --</option>
                                    <option value="1">1 Month</option>
                                                       <!--         <option value="2">2 Months</option>  
                                                                 <option value="3">3 Months</option>  -->


                                </select>
                            </div>
                            <div class="form-group mt-4">
 <!--   <label for="bank_account_id" class="block text-white-700">Bank Account:</label>

    @if($hasBankAccounts)
        <select name="bank_account_id" id="bank_account_id" class="form-control block text-black w-full mt-1 border-gray-300 rounded-md shadow-sm" required>
            <option value="">-- Select Bank Account --</option>
            @foreach($userBankAccounts as $account)
                <option value="{{ $account->id }}">
                    {{ $account->bank_name }} - {{ $account->account_number }} ({{ $account->account_holder_name }})
                </option>
            @endforeach
        </select>

        <a href="{{ route('accountdetails.index') }}" class="text-blue-600 text-sm mt-1 inline-block">
            + Add a new bank account
        </a>
    @else
        <p class="text-red-600 text-sm">⚠️ You don’t have a bank account linked yet.</p>
        <a href="{{ route('accountdetails.index') }}" class="btn btn-primary mt-2">
            Add Bank Account
        </a>
    @endif
</div> -->



                            <div id="loan_summary" class="mt-6 bg-white-800 text-dark rounded-lg shadow-lg hidden">
                                <h3 class="text-2xl font-semibold mb-4">Loan Details Summary</h3>
                                <div class="mb-2"><strong>Loan Amount:</strong> R<span id="summaryAmount">0.00</span></div>
                                <div class="mb-2"><strong>Interest:</strong> R<span id="summaryInterest">0.00</span></div>
                                <div class="mb-2"><strong>Initiation Fee:</strong> R<span id="summaryInitiation">0.00</span></div>
                                <div class="mb-2"><strong>Service Fee:</strong> R<span id="summaryService">0.00</span></div>
                                <div class="mb-2 font-bold text-lg"><strong>Total Repayment:</strong> R<span id="summaryTotal">0.00</span></div>
                                <div class="mt-4">
                                    <h4 class="text-lg font-semibold mb-2">Repayment Schedule Estimate</h4>
                                    <ul id="repaymentSchedule" class="list-disc pl-6"></ul>
                                </div>
                            </div>

                            <input type="hidden" name="interest_rate" id="interest_rate">
                            <input type="hidden" name="initiation_fee" id="initiation_fee">
                            <input type="hidden" name="service_fee" id="service_fee">
                            <input type="hidden" name="total_repayment" id="total_repayment">

                           <!-- <div class="form-group mt-4">
                                <label for="credit_score_report" class="block text-white-700">Credit Score Report:</label>
                                <input type="file" name="credit_score_report" id="credit_score_report" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div> --->
                            <div class="form-group mt-4">
                                <label for="bank_statement" class="block text-white-700"> 3 Months Bank Statement:</label>
                                <input type="file" name="bank_statement" id="bank_statement" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm" required>
                            </div>
                            <div class="form-group mt-4">
                                <label for="payslips" class="block text-white-700">Payslips:</label>
                                <input type="file" name="payslips" id="payslips" class="form-control block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>

                   <div class="form-group mt-4">
    <div class="flex items-center">
        <input type="checkbox" name="terms_conditions" value="1" id="terms_conditions" class="form-check-input" required>
        <label for="terms_conditions" class="ml-2 text-white-700">
            I accept the 
            <a href="{{ route('terms.show') }}" target="_blank" rel="noopener" class="text-blue-500">
                loan application terms &amp; conditions (pre-contract)
            </a>
        </label>
    </div>
</div>


                            <div class="form-group mt-6">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                    Submit Loan Application
                                </button>
                            </div>
                        </form>

                        <script>
                            const baseInitiation = {{ $feeRules['initiation']?->flat_fee ?? 165 }};
                            const initiationRate = {{ $feeRules['initiation']?->rate ?? 0.10 }};
                            const maxInitiation = {{ $feeRules['initiation']?->cap ?? 1050 }};
                            const serviceFee = {{ $feeRules['service']?->flat_fee ?? 60 }};
                            const interestRate = {{ $feeRules['interest']?->rate ?? 0.05 }};

                            const amountField = document.getElementById('loan_amount');
                            const monthsField = document.getElementById('months');

                            function updateLoanSummary() {
                                const amount = parseFloat(amountField.value);
                                const months = parseInt(monthsField.value);
                                if (!amount || !months) return;

                                const interest = amount * interestRate*months;
                                const excess = amount > 1000 ? amount - 1000 : 0;
                                let initiation = baseInitiation + (excess * initiationRate);
                                initiation = Math.min(initiation, maxInitiation);
                                const total = amount + interest + initiation + serviceFee;
                                const monthly = (total / months).toFixed(2);

                                document.getElementById('loan_summary').classList.remove('hidden');
                                document.getElementById('summaryAmount').innerText = amount.toFixed(2);
                                document.getElementById('summaryInterest').innerText = interest.toFixed(2);
                                document.getElementById('summaryInitiation').innerText = initiation.toFixed(2);
                                document.getElementById('summaryService').innerText = serviceFee.toFixed(2);
                                document.getElementById('summaryTotal').innerText = total.toFixed(2);

                                document.getElementById('interest_rate').value = interestRate;
                                document.getElementById('initiation_fee').value = initiation.toFixed(2);
                                document.getElementById('service_fee').value = serviceFee.toFixed(2);
                                document.getElementById('total_repayment').value = total.toFixed(2);

                                const schedule = document.getElementById('repaymentSchedule');
                                schedule.innerHTML = '';
                                for (let i = 1; i <= months; i++) {
                                    const li = document.createElement('li');
                                    li.innerText = `Month ${i}: R${monthly}`;
                                    schedule.appendChild(li);
                                }
                            }

                            amountField.addEventListener('input', updateLoanSummary);
                            monthsField.addEventListener('change', updateLoanSummary);

                            function handlePurposeChange(select) {
                                const otherDiv = document.getElementById('other_purpose_div');
                                if (select.value === 'Other') {
                                    otherDiv.classList.remove('hidden');
                                } else {
                                    otherDiv.classList.add('hidden');
                                    document.getElementById('other_purpose').value = '';
                                }
                            }
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
