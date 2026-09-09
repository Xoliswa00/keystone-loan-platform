<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use App\Services\Export\DatasetRegistry;
use App\Services\Export\ExportProfileRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private DatasetRegistry $registry) {}

    public function index()
    {
        $profiles = ExportProfile::with('recipient')->withCount('runs')->orderBy('name')->get();

        return view('admin.exports.profiles.index', compact('profiles'));
    }

    public function create()
    {
        return view('admin.exports.profiles.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $profile = ExportProfile::create($data + ['created_by' => $request->user()->id]);

        AuditLog::record('created', $profile, [], $data);

        return redirect()->route('admin.exports.profiles.index')
            ->with('success', "Profile \"{$profile->name}\" created.");
    }

    public function edit(ExportProfile $profile)
    {
        return view('admin.exports.profiles.edit', $this->formData() + compact('profile'));
    }

    public function update(Request $request, ExportProfile $profile)
    {
        $data = $this->validated($request, $profile->id);

        $old = $profile->only(array_keys($data));
        $profile->update($data);

        AuditLog::record('updated', $profile, $old, $data);

        return redirect()->route('admin.exports.profiles.index')
            ->with('success', "Profile \"{$profile->name}\" updated.");
    }

    public function toggleActive(ExportProfile $profile)
    {
        $new = ! $profile->active;
        $profile->update(['active' => $new]);

        AuditLog::record($new ? 'activated' : 'deactivated', $profile, [], []);

        return back()->with('success', "\"{$profile->name}\" is now ".($new ? 'active' : 'inactive').'.');
    }

    public function preview(Request $request, ExportProfile $profile, ExportProfileRunner $runner)
    {
        $period = $request->input('period') ?: $profile->periodFor(now());

        $run = $runner->run($profile, $period, (string) $request->user()->id.':preview', dryRun: true);

        $rows = [];
        if ($run->hasFile()) {
            $lines = preg_split('/\r\n|\n/', trim(Storage::disk($run->file_disk)->get($run->file_path)));
            $rows = array_slice($lines, 0, 21);
        }

        return view('admin.exports.profiles.preview', compact('profile', 'run', 'rows', 'period'));
    }

    // ── validation ────────────────────────────────────────────────────────────

    protected function formData(): array
    {
        return [
            'recipients' => ExportRecipient::orderBy('name')->get(),
            'datasets' => $this->registry->all(),
        ];
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'export_recipient_id' => ['required', 'exists:export_recipients,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('export_profiles', 'slug')->ignore($ignoreId)],
            'dataset' => ['required', Rule::in(array_keys($this->registry->all()))],
            'format' => ['required', Rule::in(array_keys(ExportProfile::FORMATS))],
            'delimiter' => ['required', 'string', 'max:4'],
            'enclosure' => ['required', 'string', 'max:2'],
            'line_ending' => ['required', Rule::in(['lf', 'crlf'])],
            'filename_pattern' => ['required', 'string', 'max:190'],
            'schedule_day_of_month' => ['required', 'integer', 'between:1,28'],
            'period_offset' => ['required', 'integer', 'between:0,12'],
            'columns_json' => ['required', 'string'],
        ]);

        $recipient = ExportRecipient::findOrFail($v['export_recipient_id']);
        $columns = $this->parseColumns($v['columns_json'], $v['dataset'], $recipient->kind);
        $this->assertFilenamePattern($v['filename_pattern']);

        // A credit-bureau feed must never go out over email.
        if ($recipient->kind === 'bureau' && $recipient->transport === 'email') {
            throw ValidationException::withMessages([
                'export_recipient_id' => 'This recipient uses the email transport, which is not permitted for a credit bureau. Change the recipient transport first.',
            ]);
        }

        return [
            'export_recipient_id' => $recipient->id,
            'name' => $v['name'],
            'slug' => $v['slug'],
            'dataset' => $v['dataset'],
            'format' => $v['format'],
            'delimiter' => $v['delimiter'],
            'enclosure' => $v['enclosure'],
            'include_header' => $request->boolean('include_header'),
            'line_ending' => $v['line_ending'],
            'columns' => $columns,
            'filters' => $this->parseFilters($request->input('filters', []), $v['dataset']),
            'filename_pattern' => $v['filename_pattern'],
            'schedule_day_of_month' => $v['schedule_day_of_month'],
            'period_offset' => $v['period_offset'],
            'active' => $request->boolean('active'),
        ];
    }

    private function parseColumns(string $json, string $dataset, string $recipientKind): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || $decoded === []) {
            throw ValidationException::withMessages(['columns_json' => 'Select at least one column.']);
        }

        $allowedFormats = array_keys(ExportProfile::COLUMN_FORMATS);
        $out = [];

        foreach ($decoded as $i => $col) {
            $source = $col['source'] ?? null;
            $format = $col['format'] ?? 'string';
            $label = trim((string) ($col['label'] ?? $source));

            if (! $source) {
                throw ValidationException::withMessages(['columns_json' => 'Column #'.($i + 1).' has no source.']);
            }

            try {
                $this->registry->assertColumnAllowed($dataset, $source, $recipientKind);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['columns_json' => $e->getMessage()]);
            }

            if (! in_array($format, $allowedFormats, true)) {
                $format = $this->registry->defaultFormat($dataset, $source);
            }

            $out[] = ['source' => $source, 'label' => $label ?: $source, 'format' => $format];
        }

        return $out;
    }

    private function parseFilters($filters, string $dataset): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $filterable = $this->registry->filterable($dataset);
        $out = [];

        foreach ($filters as $field => $value) {
            if (! in_array($field, $filterable, true) || $value === null || $value === '') {
                continue;
            }
            $out[$field] = is_array($value) ? array_values($value) : array_map('trim', explode(',', (string) $value));
        }

        return $out;
    }

    private function assertFilenamePattern(string $pattern): void
    {
        $allowed = (array) config('exports.filename_tokens', []);
        preg_match_all('/\{([a-z_]+)\}/', $pattern, $m);

        foreach ($m[1] as $token) {
            if (! in_array($token, $allowed, true)) {
                throw ValidationException::withMessages(['filename_pattern' => "Unknown filename token {{$token}}."]);
            }
        }

        if (str_contains($pattern, '..') || str_contains($pattern, '/') || str_contains($pattern, '\\')) {
            throw ValidationException::withMessages(['filename_pattern' => 'The filename pattern may not contain path separators.']);
        }
    }
}
