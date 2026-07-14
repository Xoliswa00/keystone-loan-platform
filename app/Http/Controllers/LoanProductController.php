<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index()
    {
        $products = LoanProduct::orderBy('name')->get();

        return view('admin.loan_products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.loan_products.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $product = LoanProduct::create($validated);

        AuditLog::record('created', $product, [], $validated);

        return redirect()->route('loan-products.index')
            ->with('success', "Loan product \"{$product->name}\" created.");
    }

    public function edit(LoanProduct $loanProduct)
    {
        return view('admin.loan_products.edit', ['product' => $loanProduct]);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $validated = $this->validated($request, $loanProduct->id);

        $old = $loanProduct->only(array_keys($validated));
        $loanProduct->update($validated);

        AuditLog::record('updated', $loanProduct, $old, $validated);

        return redirect()->route('loan-products.index')
            ->with('success', "Loan product \"{$loanProduct->name}\" updated.");
    }

    /**
     * Quick on/off switch from the index list — the everyone-gets-it lever.
     * Per-client overrides for individually-eligible clients live on the
     * customer profile (CustomerController::toggleExtendedTerms), and stay
     * in effect regardless of this flag.
     */
    public function toggleActive(LoanProduct $loanProduct)
    {
        $new = ! $loanProduct->active;
        $loanProduct->update(['active' => $new]);

        AuditLog::record($new ? 'activated' : 'deactivated', $loanProduct, [], []);

        return back()->with('success', "\"{$loanProduct->name}\" is now ".($new ? 'active' : 'inactive').'.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:loan_products,code'.($ignoreId ? ",{$ignoreId}" : '')],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_months' => ['required', 'integer', 'min:1'],
            'max_months' => ['required', 'integer', 'gte:min_months'],
            'monthly_interest_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'initiation_fee_flat' => ['required', 'numeric', 'min:0'],
            'initiation_fee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'initiation_fee_cap' => ['required', 'numeric', 'min:0'],
            'monthly_service_fee' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Checkboxes: absent from the request entirely when unchecked, so
        // they're handled outside the validator via Request::boolean().
        $validated['requires_enhanced_affordability'] = $request->boolean('requires_enhanced_affordability');
        $validated['active'] = $request->boolean('active');

        return $validated;
    }
}
