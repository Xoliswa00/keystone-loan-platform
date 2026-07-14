<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Services\CloDecisionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOpsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // CLO re-assessment — evaluate() only ever ran once, at submission
    // (LoanApplicationObserver::created()). Facts change after that (a
    // document gets verified, another loan settles) and the stored decision
    // goes stale with nothing to refresh it. This lets a reviewer pull a
    // current read without resubmitting the application. Still advisory
    // only — same as the original evaluation, this never touches status.
    // ──────────────────────────────────────────────────────────────────────────

    public function reassess(LoanApplication $application, CloDecisionEngine $clo)
    {
        $decision = $clo->evaluate($application);

        return back()->with('success', "CLO re-assessment complete: {$decision['decision']} (risk {$decision['risk_score']}/100).");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admin notes on applications
    // ──────────────────────────────────────────────────────────────────────────

    public function addNote(Request $request, LoanApplication $application)
    {
        $request->validate([
            'note' => 'required|string|max:2000',
            'note_type' => 'nullable|in:general,affordability,document,collections,legal',
        ]);

        DB::table('admin_notes')->insert([
            'loan_application_id' => $application->id,
            'user_id' => Auth::id(),
            'note' => $request->note,
            'note_type' => $request->note_type ?? 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\AuditLog::record('note_added', $application, [], [
            'note_type' => $request->note_type ?? 'general',
        ], substr($request->note, 0, 100));

        return back()->with('success', 'Note added.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Assign application to a loan officer
    // ──────────────────────────────────────────────────────────────────────────

    public function assign(Request $request, LoanApplication $application)
    {
        $request->validate([
            'reviewer_id' => 'required|exists:users,id',
        ]);

        $old = $application->reviewer_id;
        $application->update([
            'reviewer_id' => $request->reviewer_id,
            'status' => 'under_review',
        ]);

        \App\Models\AuditLog::record('assigned', $application,
            ['reviewer_id' => $old],
            ['reviewer_id' => $request->reviewer_id]
        );

        return back()->with('success', 'Application assigned.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Collections queue — overdue accounts needing contact
    // ──────────────────────────────────────────────────────────────────────────

    public function collectionsQueue()
    {
        $overdue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->leftJoin('admin_notes as an', function ($j) {
                $j->on('an.loan_application_id', '=', 'l.loan_application_id')
                    ->where('an.note_type', 'collections')
                    ->whereRaw('an.id = (SELECT MAX(id) FROM admin_notes WHERE loan_application_id = l.loan_application_id AND note_type = "collections")');
            })
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', now())
            ->select(
                'l.id as loan_id',
                'l.loan_application_id',
                'u.name as client_name',
                'u.phone',
                'u.email',
                'c.customer_code',
                'l.remaining_balance',
                DB::raw('SUM(rs.emi_amount) as arrears_total'),
                DB::raw('DATEDIFF(CURDATE(), MIN(rs.due_date)) as days_overdue'),
                DB::raw('COUNT(rs.id) as instalments_missed'),
                'an.note as last_contact_note',
                'an.created_at as last_contact_date'
            )
            ->groupBy('l.id', 'l.loan_application_id', 'u.name', 'u.phone', 'u.email',
                'c.customer_code', 'l.remaining_balance', 'an.note', 'an.created_at')
            ->orderByDesc('days_overdue')
            ->paginate(30);

        return view('admin.collections', compact('overdue'));
    }

    /**
     * Log a collection contact attempt (call, WhatsApp, email).
     */
    public function logContactAttempt(Request $request)
    {
        $request->validate([
            'loan_application_id' => 'required|exists:loan_applications,id',
            'contact_method' => 'required|in:call,whatsapp,email,sms,visit',
            'outcome' => 'required|in:reached,no_answer,promised_payment,disputed,legal_referral',
            'note' => 'nullable|string|max:1000',
            'next_contact_date' => 'nullable|date|after:today',
        ]);

        $application = LoanApplication::findOrFail($request->loan_application_id);

        DB::table('admin_notes')->insert([
            'loan_application_id' => $application->id,
            'user_id' => Auth::id(),
            'note' => "[{$request->contact_method}] Outcome: {$request->outcome}. ".($request->note ?? ''),
            'note_type' => 'collections',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\AuditLog::record('collections_contact', $application, [], [
            'method' => $request->contact_method,
            'outcome' => $request->outcome,
        ]);

        return back()->with('success', 'Contact attempt logged.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NCR export download (generate and return as file download)
    // ──────────────────────────────────────────────────────────────────────────

    public function ncrExportDownload(Request $request, \App\Services\NcrExportService $export)
    {
        $quarter = $request->quarter ?? now()->format('Y').'-Q'.ceil(now()->month / 3);

        try {
            $loans = $export->loansForQuarter($quarter);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $filename = "NCR_Return_{$quarter}_".now()->format('Ymd').'.csv';

        return response($export->toCsv($loans), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
