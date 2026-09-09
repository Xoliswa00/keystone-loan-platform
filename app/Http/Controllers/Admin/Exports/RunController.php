<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use App\Models\ExportRun;
use App\Services\Export\ExportProfileRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function dashboard()
    {
        $recentRuns = ExportRun::with('profile.recipient')->latest()->limit(15)->get();
        $activeProfiles = ExportProfile::where('active', true)->count();
        $activeRecipients = ExportRecipient::where('active', true)->count();
        $openDisputes = \App\Models\CreditReportDispute::where('status', 'open')->count();
        $enabled = (bool) config('exports.enabled');
        $snapshotsEnabled = (bool) config('exports.snapshots_enabled');

        return view('admin.exports.dashboard', compact(
            'recentRuns', 'activeProfiles', 'activeRecipients', 'openDisputes', 'enabled', 'snapshotsEnabled'
        ));
    }

    public function index(Request $request)
    {
        $runs = ExportRun::with('profile.recipient')
            ->when($request->input('profile'), fn ($q, $slug) => $q->whereHas('profile', fn ($p) => $p->where('slug', $slug)))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('period'), fn ($q, $p) => $q->where('period', $p))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $profiles = ExportProfile::orderBy('name')->get();

        return view('admin.exports.runs.index', compact('runs', 'profiles'));
    }

    public function show(ExportRun $run)
    {
        $run->load('profile.recipient');

        return view('admin.exports.runs.show', compact('run'));
    }

    public function download(ExportRun $run): StreamedResponse
    {
        abort_unless($run->hasFile(), 404, 'The file for this run is no longer available.');

        AuditLog::record('downloaded', $run, [], ['file' => basename($run->file_path), 'period' => $run->period]);

        return Storage::disk($run->file_disk)->download($run->file_path, basename($run->file_path));
    }

    public function rerun(ExportRun $run, ExportProfileRunner $runner)
    {
        if (! config('exports.enabled')) {
            return back()->with('error', 'Outbound data exports are disabled (DATA_EXPORTS_ENABLED=false).');
        }

        $new = $runner->run($run->profile, $run->period, (string) request()->user()->id.':rerun');

        return redirect()->route('admin.exports.runs.show', $new)
            ->with($new->status === 'success' ? 'success' : 'error',
                "Re-run finished with status \"{$new->status}\".");
    }
}
