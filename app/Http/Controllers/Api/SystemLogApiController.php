<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class SystemLogApiController extends Controller
{
    /**
     * GET /api/admin/system-logs?level=error&since=2026-07-01&per_page=50
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'level' => 'nullable|in:debug,info,notice,warning,error,critical,alert,emergency',
            'since' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $logs = SystemLog::query()
            ->level($validated['level'] ?? null)
            ->when($validated['since'] ?? null, fn ($q, $since) => $q->where('logged_at', '>=', $since))
            ->orderByDesc('logged_at')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json($logs);
    }
}
