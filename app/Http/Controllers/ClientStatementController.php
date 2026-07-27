<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClientStatement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientStatementController extends Controller
{
    /**
     * Client requests their own statement.
     * Uses cached version if available; queues generation otherwise.
     */
    public function myStatement(Request $request)
    {
        return $this->requestStatement(Auth::user(), $request->boolean('email'));
    }

    /**
     * Admin downloads a statement for any client.
     */
    public function download(User $user)
    {
        if (! Auth::user()->hasRole('admin', 'loan_officer', 'finance')) {
            abort(403);
        }

        return $this->requestStatement($user, false);
    }

    /**
     * Polling endpoint — client checks if their statement is ready.
     */
    public function checkStatus()
    {
        $user = Auth::user();
        $period = now()->format('Y-m');
        $path = Cache::get("statement_{$user->id}_{$period}");

        if ($path && Storage::disk('local')->exists($path)) {
            return response()->json([
                'ready' => true,
                'download' => route('client.statement.file', ['user' => $user->id, 'period' => $period]),
            ]);
        }

        $generating = Cache::get("statement_generating_{$user->id}_{$period}");

        return response()->json([
            'ready' => false,
            'generating' => (bool) $generating,
        ]);
    }

    /**
     * Serve the actual PDF file (fast — from storage, no regeneration).
     */
    public function serveFile(User $user, string $period)
    {
        // Auth check
        if (Auth::id() !== $user->id && ! Auth::user()->hasRole('admin', 'finance')) {
            abort(403);
        }

        $path = Cache::get("statement_{$user->id}_{$period}");

        if (! $path) {
            // Try the expected path directly
            $filename = 'KCP-Statement-'.str_pad($user->id, 6, '0', STR_PAD_LEFT).'-'.$period.'.pdf';
            $path = "statements/{$user->id}/{$filename}";
        }

        if (! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Statement not found. Please request a new one.');
        }

        $filename = 'KCP-Statement-'.str_pad($user->id, 6, '0', STR_PAD_LEFT).'-'.$period.'.pdf';

        return response()->download(
            Storage::disk('local')->path($path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function requestStatement(User $user, bool $emailUser = false)
    {
        $period = now()->format('Y-m');
        $cacheKey = "statement_{$user->id}_{$period}";
        $genKey = "statement_generating_{$user->id}_{$period}";

        // 1. Check if already cached and ready
        $cachedPath = Cache::get($cacheKey);
        if ($cachedPath && Storage::disk('local')->exists($cachedPath)) {
            $filename = basename($cachedPath);

            return response()->download(
                Storage::disk('local')->path($cachedPath),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        // 2. Check if already generating
        if (Cache::get($genKey)) {
            return back()->with('info',
                'Your statement is being generated. This usually takes under 30 seconds. '.
                'Refresh the page in a moment or you will receive an email when ready.'
            );
        }

        // 3. Queue generation
        Cache::put($genKey, true, now()->addMinutes(5));

        try {
            GenerateClientStatement::dispatch($user->id, $period, $emailUser);
        } catch (\Exception $e) {
            Cache::forget($genKey);
            Log::error('Failed to queue statement generation: '.$e->getMessage());

            return back()->with('error', 'Could not generate statement. Please try again.');
        }

        // Return a "generating" page that polls for completion
        return view('statements.generating', [
            'user' => $user,
            'period' => $period,
            'period_label' => now()->format('F Y'),
            'poll_url' => route('client.statement.status'),
            'download_url' => route('client.statement.file', ['user' => $user->id, 'period' => $period]),
        ]);
    }
}
