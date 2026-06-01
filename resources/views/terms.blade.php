<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Loan Application Terms & Conditions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-2xl font-semibold mb-4">Terms & Conditions</h3>
                
                <p class="mb-4">
                    By submitting a loan application, you agree to the following terms and conditions. These terms are binding and must be accepted prior to loan approval:
                </p>

                <ol class="list-decimal list-inside mb-4 space-y-2">
                    <li><strong>Eligibility:</strong> You must be at least 18 years old, a legal resident, and provide valid identification and proof of income.</li>
                    <li><strong>Truthful Information:</strong> All information provided in the application must be true and complete. Any misrepresentation may lead to application denial or legal action.</li>
                    <li><strong>Credit Check:</strong> We reserve the right to perform credit assessments and background checks before approving your loan.</li>
                    <li><strong>Loan Purpose:</strong> The loan must be used for the purpose indicated in your application. Misuse may result in penalties or loan recall.</li>
                    <li><strong>Fees & Interest:</strong> Applicable fees, interest rates, and repayment schedules are disclosed in the loan summary. You agree to comply with all repayment terms.</li>
                    <li><strong>Confidentiality:</strong> Your personal and financial information will be treated with strict confidentiality and used solely for loan processing purposes.</li>
                    <li><strong>Default:</strong> Failure to make timely payments may result in additional charges, legal action, and reporting to credit bureaus.</li>
                    <li><strong>Amendments:</strong> These terms may be updated from time to time. Continued use of our services constitutes acceptance of any changes.</li>
                    <li><strong>Governing Law:</strong> All loans are governed by the laws of [Your Country/Region]. Any disputes will be subject to the appropriate courts.</li>
                    <li><strong>Pre-Contract Acknowledgment:</strong> By reading and accepting these terms, you acknowledge understanding of the rules and conditions of your loan prior to signing the official contract.</li>
                </ol>

                <p class="mt-4">
                    Please read carefully. You must accept these terms before proceeding with your loan application. If you do not agree with these terms, do not submit a loan application.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
