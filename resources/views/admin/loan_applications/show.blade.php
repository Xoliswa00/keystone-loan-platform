<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Review Application - #{{ $application->id }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">{{ session('error') }}</div>
        @endif

        {{-- 1. Financial & Risk Summary --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow text-center">
                <p class="text-sm text-gray-500 dark:text-gray-300">Loan Amount</p>
                <p class="font-bold text-lg text-green-700 dark:text-green-300">R{{ number_format($application->loan_amount, 2) }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow text-center">
                <p class="text-sm text-gray-500 dark:text-gray-300">Total Fees</p>
                <p class="font-bold text-lg text-green-700 dark:text-green-300">R{{ number_format($application->loanfee->total_due ?? 0, 2) }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow text-center">
                <p class="text-sm text-gray-500 dark:text-gray-300">Outstanding Balance</p>
                <p class="font-bold text-lg text-red-600 dark:text-red-400">
                    R{{ number_format($application->user->customer->current_balance, 2) }}
                </p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow text-center">
                <p class="text-sm text-gray-500 dark:text-gray-300">Previous Rejections</p>
                <p class="font-bold text-lg text-red-600 dark:text-red-400">{{ $application->user->loanApplications()->where('status', 'rejected')->count() }}</p>
            </div>
        </section>

        {{-- 2. Applicant Info & Loan Status --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow">
                        <p class="text-sm text-gray-500 dark:text-gray-300">Applicant</p>
                    
                        <p class="font-semibold">
                            {{ $application->user->name }}
                        </p>
                    
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Customer Code: {{ $application->user->customer->customer_code ?? 'N/A' }}
                        </p>
                    
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Customer ID Number: {{ $application->user->ID_Number ?? 'N/A' }}
                        </p>
                    
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Submitted: {{ $application->created_at->format('d M Y') }}
                        </p>
                        
                           <p class="text-gray-500 dark:text-gray-400 text-sm">
                            Repayment Date: {{ $application->repaymentSchedules()->latest('due_date')->value('due_date') ?? 'N/A' }}
                        </p>
                    
@php
$phone = null;
$message = null;

if (!empty($application->user->phone)) {

    $phone = preg_replace('/\D/', '', $application->user->phone);

    // Convert SA local number to international
    if (str_starts_with($phone, '0')) {
        $phone = '27' . substr($phone, 1);
    }

    // If 9 digits assume missing country code
    if (!str_starts_with($phone, '27') && strlen($phone) === 9) {
        $phone = '27' . $phone;
    }

    $name = $application->user->name ?? 'there';

    $message = urlencode("Hi {$name}, this is Liger Finance regarding your loan application.");
}
@endphp

@if($phone)
<a 
    href="https://wa.me/{{ $phone }}?text={{ $message }}"
    target="_blank"
    rel="noopener noreferrer"
    class="mt-2 inline-flex items-center gap-1 text-green-600 hover:underline"
    aria-label="Chat with applicant on WhatsApp"
>
    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M20.52 3.48A11.94 11.94 0 0 0 12.05 0C5.4 0 .05 5.35.05 12c0 2.12.55 4.19 1.6 6.02L0 24l6.15-1.6a11.94 11.94 0 0 0 5.9 1.52h.01c6.65 0 12-5.35 12-12a11.93 11.93 0 0 0-3.54-8.44Z"/>
    </svg>

    <span class="text-sm">WhatsApp</span>
</a>
@endif
                    </div>

            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded shadow">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Notes: {{ $application->reason }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-300">Application Status</p>
                <span class="inline-flex items-center px-3 py-1 rounded text-sm font-medium
                    {{ $application->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($application->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                    {{ ucfirst($application->status) }}
                </span>
            </div>
        </section>

        {{-- 3. Loan Fees Table --}}
        @if($application->loanfee)
        <section class="overflow-x-auto mb-6">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-2">Interest Rate</th>
                        <th class="px-4 py-2">Interest Amount</th>
                        <th class="px-4 py-2">Initiation Fee</th>
                        <th class="px-4 py-2">Service Fee</th>
                        <th class="px-4 py-2 text-right">Total Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
                    <tr>
                        <td class="px-4 py-2">{{ $application->loanfee->interest_rate }}%</td>
                        <td class="px-4 py-2">R{{ number_format($application->loanfee->interest_amount,2) }}</td>
                        <td class="px-4 py-2">R{{ number_format($application->loanfee->initiation_fee,2) }}</td>
                        <td class="px-4 py-2">R{{ number_format($application->loanfee->service_fee,2) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-green-700">R{{ number_format($application->loanfee->total_due,2) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
        @endif

        {{-- 4. Previous Loans & Repayments --}}
        @php
            $previousLoans = $application->user->loanApplications()->where('id', '!=', $application->id)->get();
            $outstandingLoans = $previousLoans->where('status','approved')->where('is_fully_paid',false);
            $rejectedLoans = $previousLoans->where('status','rejected');
        @endphp

        @if($previousLoans->count())
<section class="overflow-x-auto mb-6">
    <h4 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-2">Previous Loans</h4>
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm text-left">
        <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
            <tr>
                <th class="px-4 py-2 w-16">ID</th>
                <th class="px-4 py-2 w-32">Notes</th>
                <th class="px-4 py-2 w-24">Loan Type</th>
                <th class="px-4 py-2 w-32 text-right">Disbursed</th>
                <th class="px-4 py-2 w-32 text-right">Paid</th>
                <th class="px-4 py-2 w-32 text-right">Profit</th>
                <th class="px-4 py-2 w-32">Next Due</th>
                <th class="px-4 py-2 w-24 text-center">Overdue</th>
                <th class="px-4 py-2 w-24">Status</th>
                <th class="px-4 py-2 w-32">Created</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
            @foreach($previousLoans as $loan)
            @php
                $outstandingAmount = $loan->loan_amount - $loan->repaymentSchedules()->where('status', 'paid')->sum('emi_amount');
                $nextDue = $loan->repaymentSchedules()->where('status','pending')->orderBy('due_date')->first();
                $isOverdue = $loan->repaymentSchedules()->where('status','pending')->whereDate('due_date','<',now())->exists();
                
                
                            $paidAmount = $loan->repaymentSchedules()->where('status', 'paid')->sum('emi_amount');
            $profit = $paidAmount - $loan->loan_amount;
            @endphp
            <tr>
                <td class="px-4 py-2">#{{ $loan->id }}</td>
                <td class="px-4 py-2">{{ $loan->reason ?? 'N/A' }}</td>
                <td class="px-4 py-2">{{ ucfirst($loan->loan_type) }}</td>
                <td class="px-4 py-2 text-right">R{{ number_format($loan->loan_amount, 2) }}</td>
                <td class="px-4 py-2 text-right">R{{ number_format($loan->repaymentSchedules()->where('status','paid')->sum('emi_amount'),2)}}</td>
            <td class="px-4 py-2 text-right">R{{ number_format($profit, 2) }}</td>

                <td class="px-4 py-2">{{ $nextDue->due_date ?? 'N/A' }}</td>
                <td class="px-4 py-2 text-center">
                    @if($isOverdue)
                        <span class="text-red-600 dark:text-red-400 font-semibold">Yes</span>
                    @else
                        <span class="text-green-600 dark:text-green-300 font-semibold">No</span>
                    @endif
                </td>
                <td class="px-4 py-2">{{ ucfirst($loan->status) }}</td>
                <td class="px-4 py-2">{{ $loan->created_at->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>

        {{-- Optional Footer Totals --}}
        <tfoot class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold">
            <tr>
                <td colspan="3" class="px-4 py-2 text-right">Totals</td>
                <td class="px-4 py-2 text-right">R{{ number_format($previousLoans->sum('loan_amount'),2) }}</td>
                <td class="px-4 py-2 text-right">R{{ number_format($previousLoans->sum(fn($loan) => $loan->repaymentSchedules()->where('status','paid')->sum('emi_amount')),2) }}</td>
<td class="px-4 py-2 text-right">
    R{{ number_format($previousLoans->sum(fn($loan) => abs($loan->loan_amount - $loan->repaymentSchedules()->where('status','paid')->sum('emi_amount'))), 2) }}
</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</section>


        @endif

        {{-- 5. Submitted Documents --}}
        <section class="mb-6">
            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-3">Submitted Documents</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @if($application->credit_score_report)
                    <x-admin.document-preview label="Credit Report" :path="$application->credit_score_report" />
                @endif
                @if($application->bank_statement)
                    <x-admin.document-preview label="Bank Statement" :path="$application->bank_statement" />
                @endif
                @if($application->payslips)
                    <x-admin.document-preview label="Payslip" :path="$application->payslips" />
                @endif
                @if($application->user->ID_copy)
                    <x-admin.document-preview label="ID Copy" :path="$application->user->ID_copy" />
                @endif
            </div>
        </section>

        {{-- 6. Admin Notes --}}
    {{-- 6. Admin Notes --}}
<section class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-2">
        💬 Admin Notes
    </h4>

    <form method="POST" action="{{ route('loanapplications.updateNote', $application->id) }}">
        @csrf
        @method('PUT')

        <textarea
            name="reason"
            rows="3"
            required
            class="w-full p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-sm"
            placeholder="Write an internal admin note...">{{ old('reason', $application->reason) }}</textarea>

        <button type="submit" class="btn btn-primary mt-2">
            💾 Save Note
        </button>
    </form>
</section>


        {{-- 7. Approve / Reject (Sticky) --}}
        @if($application->status=="pending")
        <section class="flex flex-wrap gap-4 mt-4 sticky top-0 bg-white dark:bg-gray-800 p-4 z-10 rounded shadow">
            <form method="POST" action="{{ route('loans.approve', $application->id) }}">
                @csrf
                <button class="btn btn-success">✅ Approve</button>
            </form>
            <form method="POST" action="{{ route('loans.reject', $application->id) }}">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Rejection reason..." required class="input input-bordered w-64">
                <button class="btn btn-danger ml-2">❌ Reject</button>
            </form>
        </section>
        @endif

    </div>
</x-app-layout>
