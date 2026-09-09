{{-- Expects: $recipient, $action, $method --}}
<form method="POST" action="{{ $action }}" class="space-y-5">
  @csrf
  @if($method === 'PUT') @method('PUT') @endif

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="kc-label">Name</label>
      <input type="text" name="name" value="{{ old('name', $recipient->name) }}" required class="kc-input @error('name') border-red-400 @enderror">
      @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Slug</label>
      <input type="text" name="slug" value="{{ old('slug', $recipient->slug) }}" required class="kc-input font-mono @error('slug') border-red-400 @enderror" placeholder="e.g. sacrra-transunion">
      @error('slug')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="kc-label">Kind</label>
      <select name="kind" class="kc-select @error('kind') border-red-400 @enderror">
        @foreach(\App\Models\ExportRecipient::KINDS as $val => $label)
          <option value="{{ $val }}" @selected(old('kind', $recipient->kind) === $val)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="text-[11px] text-kc-charcoal/70 mt-1">Only a <strong>bureau</strong> recipient may receive personal-information columns.</p>
      @error('kind')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Transport</label>
      <select name="transport" class="kc-select @error('transport') border-red-400 @enderror">
        @foreach(\App\Models\ExportRecipient::TRANSPORTS as $val => $label)
          <option value="{{ $val }}" @selected(old('transport', $recipient->transport) === $val)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="text-[11px] text-kc-charcoal/70 mt-1">Email is refused for bureau recipients and for any PII column. SFTP is Phase 2.</p>
      @error('transport')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
      <label class="kc-label">Contact person</label>
      <input type="text" name="contact_person" value="{{ old('contact_person', $recipient->contact_person) }}" class="kc-input">
    </div>
    <div>
      <label class="kc-label">Contact email</label>
      <input type="email" name="contact_email" value="{{ old('contact_email', $recipient->contact_email) }}" class="kc-input">
    </div>
    <div>
      <label class="kc-label">Contact phone</label>
      <input type="text" name="contact_phone" value="{{ old('contact_phone', $recipient->contact_phone) }}" class="kc-input">
    </div>
  </div>

  <div>
    <label class="kc-label">Email destination addresses <span class="text-kc-charcoal/60">(one per line — email transport only)</span></label>
    <textarea name="email_recipients_raw" rows="2" class="kc-input font-mono text-xs">{{ old('email_recipients_raw', collect($recipient->email_recipients ?? [])->implode("\n")) }}</textarea>
    <p class="text-[11px] text-kc-charcoal/70 mt-1">Domains must be listed in <code>DATA_EXPORTS_EMAIL_DOMAINS</code>.</p>
  </div>

  <div>
    <label class="kc-label">POPIA lawful basis</label>
    <textarea name="lawful_basis" rows="2" class="kc-input">{{ old('lawful_basis', $recipient->lawful_basis) }}</textarea>
    <p class="text-[11px] text-kc-charcoal/70 mt-1">Must be recorded before this recipient can be activated.</p>
  </div>

  <div>
    <label class="kc-label">Notes</label>
    <textarea name="notes" rows="2" class="kc-input">{{ old('notes', $recipient->notes) }}</textarea>
  </div>

  <label class="flex items-center gap-2 text-sm text-kc-charcoal/80">
    <input type="checkbox" name="active" value="1" @checked(old('active', $recipient->active)) class="rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
    Active
  </label>

  <div class="flex gap-3">
    <button type="submit" class="kc-btn-primary">{{ $recipient->exists ? 'Save Changes' : 'Create Recipient' }}</button>
    <a href="{{ route('admin.exports.recipients.index') }}" class="kc-btn-ghost">Cancel</a>
  </div>
</form>
