{{-- Expects: $profile, $recipients, $datasets, $action, $method --}}
@php
  $datasetColumns = collect($datasets)->map(fn ($d) => $d['columns'] ?? [])->all();
  $recipientKinds = $recipients->pluck('kind', 'id');
  $selectedInit = old('columns_json') ? json_decode(old('columns_json'), true) : ($profile->columns ?? []);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
  x-data="exportProfileForm({
    datasetColumns: {{ \Illuminate\Support\Js::from($datasetColumns) }},
    recipientKinds: {{ \Illuminate\Support\Js::from($recipientKinds) }},
    formats: {{ \Illuminate\Support\Js::from(\App\Models\ExportProfile::COLUMN_FORMATS) }},
    dataset: @js(old('dataset', $profile->dataset ?? 'account_snapshot')),
    recipientId: @js((string) old('export_recipient_id', $profile->export_recipient_id ?? '')),
    selected: {{ \Illuminate\Support\Js::from($selectedInit ?: []) }},
  })">
  @csrf
  @if($method === 'PUT') @method('PUT') @endif

  @error('columns_json')<div class="kc-alert-error">{{ $message }}</div>@enderror
  @error('filename_pattern')<div class="kc-alert-error">{{ $message }}</div>@enderror
  @error('export_recipient_id')<div class="kc-alert-error">{{ $message }}</div>@enderror

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="kc-label">Name</label>
      <input type="text" name="name" value="{{ old('name', $profile->name) }}" required class="kc-input">
      @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="kc-label">Slug</label>
      <input type="text" name="slug" value="{{ old('slug', $profile->slug) }}" required class="kc-input font-mono">
      @error('slug')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="kc-label">Recipient</label>
      <select name="export_recipient_id" x-model="recipientId" class="kc-select" required>
        <option value="">— select —</option>
        @foreach($recipients as $r)
          <option value="{{ $r->id }}">{{ $r->name }} ({{ \App\Models\ExportRecipient::KINDS[$r->kind] ?? $r->kind }})</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="kc-label">Dataset</label>
      <select name="dataset" x-model="dataset" class="kc-select" required>
        @foreach($datasets as $key => $d)
          <option value="{{ $key }}">{{ $d['label'] ?? $key }}</option>
        @endforeach
      </select>
    </div>
  </div>

  {{-- ── Column picker ─────────────────────────────────────────────── --}}
  <div>
    <label class="kc-label">Columns <span class="text-kc-charcoal/60">(order = file column order)</span></label>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-1">

      <div class="border border-kc-silver rounded-lg p-3">
        <p class="text-xs font-semibold text-kc-charcoal/70 mb-2">Available</p>
        <div class="flex gap-2">
          <select x-ref="add" class="kc-select text-xs">
            <template x-for="col in available()" :key="col">
              <option :value="col" x-text="col"></option>
            </template>
          </select>
          <button type="button" class="kc-btn-ghost text-xs" @click="add($refs.add.value)">Add</button>
        </div>
        <p class="text-[11px] text-kc-charcoal/60 mt-2" x-show="available().length === 0">
          All permitted columns for this recipient are selected.
        </p>
      </div>

      <div class="border border-kc-silver rounded-lg p-3">
        <p class="text-xs font-semibold text-kc-charcoal/70 mb-2">Selected (<span x-text="selected.length"></span>)</p>
        <template x-if="selected.length === 0">
          <p class="text-[11px] text-red-600">Add at least one column.</p>
        </template>
        <ul class="space-y-2">
          <template x-for="(col, i) in selected" :key="col.source + i">
            <li class="flex flex-wrap items-center gap-2 text-xs">
              <span class="font-mono text-kc-navy w-40 shrink-0" x-text="col.source"></span>
              <input type="text" class="kc-input text-xs !py-1 w-32" x-model="col.label" placeholder="label">
              <select class="kc-select text-xs !py-1 w-40" x-model="col.format">
                <template x-for="(fLabel, fVal) in formats" :key="fVal">
                  <option :value="fVal" x-text="fLabel"></option>
                </template>
              </select>
              <span class="ml-auto flex gap-1">
                <button type="button" class="kc-btn-ghost text-[10px] !py-0.5 !px-1.5" @click="move(i, -1)" :disabled="i === 0">▲</button>
                <button type="button" class="kc-btn-ghost text-[10px] !py-0.5 !px-1.5" @click="move(i, 1)" :disabled="i === selected.length - 1">▼</button>
                <button type="button" class="kc-btn-ghost text-[10px] !py-0.5 !px-1.5" @click="selected.splice(i, 1)">✕</button>
              </span>
            </li>
          </template>
        </ul>
      </div>
    </div>
    <input type="hidden" name="columns_json" :value="JSON.stringify(selected)">
  </div>

  {{-- ── Format ───────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div>
      <label class="kc-label">Format</label>
      <select name="format" class="kc-select">
        @foreach(\App\Models\ExportProfile::FORMATS as $val => $label)
          <option value="{{ $val }}" @selected(old('format', $profile->format ?? 'csv') === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="kc-label">Delimiter</label>
      <input type="text" name="delimiter" value="{{ old('delimiter', $profile->delimiter ?? ',') }}" maxlength="4" class="kc-input font-mono">
    </div>
    <div>
      <label class="kc-label">Enclosure</label>
      <input type="text" name="enclosure" value="{{ old('enclosure', $profile->enclosure ?? '"') }}" maxlength="2" class="kc-input font-mono">
    </div>
    <div>
      <label class="kc-label">Line ending</label>
      <select name="line_ending" class="kc-select">
        <option value="lf" @selected(old('line_ending', $profile->line_ending ?? 'lf') === 'lf')>LF (Unix)</option>
        <option value="crlf" @selected(old('line_ending', $profile->line_ending ?? 'lf') === 'crlf')>CRLF (Windows)</option>
      </select>
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 items-end">
    <div class="sm:col-span-2">
      <label class="kc-label">Filename pattern</label>
      <input type="text" name="filename_pattern" value="{{ old('filename_pattern', $profile->filename_pattern ?? '{recipient_slug}_{profile_slug}_{period}.csv') }}" class="kc-input font-mono text-xs">
      <p class="text-[11px] text-kc-charcoal/70 mt-1">Tokens: {{ collect(config('exports.filename_tokens'))->map(fn($t) => '{'.$t.'}')->implode(' ') }}</p>
    </div>
    <div>
      <label class="kc-label">Day of month</label>
      <input type="number" name="schedule_day_of_month" min="1" max="28" value="{{ old('schedule_day_of_month', $profile->schedule_day_of_month ?? 5) }}" class="kc-input">
    </div>
    <div>
      <label class="kc-label">Period offset (months)</label>
      <input type="number" name="period_offset" min="0" max="12" value="{{ old('period_offset', $profile->period_offset ?? 1) }}" class="kc-input">
    </div>
  </div>

  <div class="flex flex-wrap gap-6">
    <label class="flex items-center gap-2 text-sm text-kc-charcoal/80">
      <input type="checkbox" name="include_header" value="1" @checked(old('include_header', $profile->include_header ?? true)) class="rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
      Include header row
    </label>
    <label class="flex items-center gap-2 text-sm text-kc-charcoal/80">
      <input type="checkbox" name="active" value="1" @checked(old('active', $profile->active ?? false)) class="rounded border-kc-silver text-kc-gold focus:ring-kc-gold/30">
      Active
    </label>
  </div>

  <div class="flex gap-3">
    <button type="submit" class="kc-btn-primary">{{ $profile->exists ? 'Save Changes' : 'Create Profile' }}</button>
    <a href="{{ route('admin.exports.profiles.index') }}" class="kc-btn-ghost">Cancel</a>
  </div>
</form>

<script>
  function exportProfileForm(cfg) {
    return {
      datasetColumns: cfg.datasetColumns,
      recipientKinds: cfg.recipientKinds,
      formats: cfg.formats,
      dataset: cfg.dataset,
      recipientId: cfg.recipientId,
      selected: cfg.selected || [],
      kind() {
        return this.recipientKinds[this.recipientId] || 'partner';
      },
      permitted(source) {
        const meta = (this.datasetColumns[this.dataset] || {})[source] || {};
        if (meta.internal && this.kind() !== 'internal') return false;
        if (meta.pii && this.kind() !== 'bureau') return false;
        return true;
      },
      available() {
        const chosen = this.selected.map(c => c.source);
        return Object.keys(this.datasetColumns[this.dataset] || {})
          .filter(s => !chosen.includes(s) && this.permitted(s));
      },
      add(source) {
        if (!source || this.selected.some(c => c.source === source)) return;
        const meta = (this.datasetColumns[this.dataset] || {})[source] || {};
        this.selected.push({ source, label: source, format: meta.type || 'string' });
      },
      move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= this.selected.length) return;
        const t = this.selected[i];
        this.selected[i] = this.selected[j];
        this.selected[j] = t;
      },
    };
  }
</script>
