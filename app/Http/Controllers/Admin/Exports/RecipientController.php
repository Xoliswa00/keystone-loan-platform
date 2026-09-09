<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExportRecipient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecipientController extends Controller
{
    public function index()
    {
        $recipients = ExportRecipient::withCount('profiles')->orderBy('name')->get();

        return view('admin.exports.recipients.index', compact('recipients'));
    }

    public function create()
    {
        return view('admin.exports.recipients.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $recipient = ExportRecipient::create($data + ['created_by' => $request->user()->id]);

        AuditLog::record('created', $recipient, [], $data);

        return redirect()->route('admin.exports.recipients.index')
            ->with('success', "Recipient \"{$recipient->name}\" created.");
    }

    public function edit(ExportRecipient $recipient)
    {
        return view('admin.exports.recipients.edit', compact('recipient'));
    }

    public function update(Request $request, ExportRecipient $recipient)
    {
        $data = $this->validated($request, $recipient->id);

        $old = $recipient->only(array_keys($data));
        $recipient->update($data);

        AuditLog::record('updated', $recipient, $old, $data);

        return redirect()->route('admin.exports.recipients.index')
            ->with('success', "Recipient \"{$recipient->name}\" updated.");
    }

    public function toggleActive(ExportRecipient $recipient)
    {
        if (! $recipient->active && blank($recipient->lawful_basis)) {
            return back()->with('error', 'Record a POPIA lawful basis before activating this recipient.');
        }

        $new = ! $recipient->active;
        $recipient->update(['active' => $new]);

        AuditLog::record($new ? 'activated' : 'deactivated', $recipient, [], []);

        return back()->with('success', "\"{$recipient->name}\" is now ".($new ? 'active' : 'inactive').'.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('export_recipients', 'slug')->ignore($ignoreId)],
            'kind' => ['required', Rule::in(array_keys(ExportRecipient::KINDS))],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'transport' => ['required', Rule::in(array_keys(ExportRecipient::TRANSPORTS))],
            'email_recipients_raw' => ['nullable', 'string', 'max:2000'],
            'lawful_basis' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) $request->input('email_recipients_raw')))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()->all();

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'kind' => $validated['kind'],
            'contact_person' => $validated['contact_person'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'transport' => $validated['transport'],
            'email_recipients' => $emails,
            'lawful_basis' => $validated['lawful_basis'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $request->boolean('active') && filled($validated['lawful_basis'] ?? null),
        ];
    }
}
