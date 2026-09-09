<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditReportDispute;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function index()
    {
        $disputes = CreditReportDispute::with(['loan.user', 'raisedBy'])
            ->orderByRaw("status = 'open' desc")
            ->orderBy('respond_by')
            ->paginate(30);

        return view('admin.exports.disputes.index', compact('disputes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $dispute = CreditReportDispute::create([
            'loan_id' => $data['loan_id'],
            'raised_by' => $request->user()->id,
            'raised_at' => now(),
            // NCR Reg 18: 20 business days to investigate.
            'respond_by' => now()->addWeekdays(20)->toDateString(),
            'reason' => $data['reason'],
            'reference' => $data['reference'] ?? null,
            'status' => 'open',
        ]);

        AuditLog::record('dispute_raised', $dispute, [], $data);

        return back()->with('success', "Dispute logged for loan #{$dispute->loan_id}. Disputed data is held back from the next export.");
    }

    public function resolve(Request $request, CreditReportDispute $dispute)
    {
        if ($dispute->status === 'resolved') {
            return back()->with('error', 'This dispute is already resolved.');
        }

        $data = $request->validate([
            'resolution_note' => ['required', 'string', 'max:2000'],
        ]);

        $dispute->update([
            'status' => 'resolved',
            'resolution_note' => $data['resolution_note'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        AuditLog::record('dispute_resolved', $dispute, [], $data);

        return back()->with('success', 'Dispute resolved. The corrected record flows out in the next scheduled export.');
    }
}
