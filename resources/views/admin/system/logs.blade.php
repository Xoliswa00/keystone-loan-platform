<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">System Logs</span>
    <p class="kc-page-subtitle">Application errors, warnings, and failed jobs — no SSH required</p>
  </x-slot>

  @if(session('success'))
    <div class="kc-alert-success mb-5 whitespace-pre-line">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="kc-alert-error mb-5 whitespace-pre-line">{{ session('error') }}</div>
  @endif

  {{-- System health --}}
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    @foreach([
      ['Queue Pending',   $health['queue_pending'], 'kc-badge-gold'],
      ['Failed Jobs',     $health['queue_failed'],  $health['queue_failed'] > 0 ? 'kc-badge-red' : 'kc-badge-green'],
      ['Log Size',        $health['log_size'],       'kc-badge-silver'],
      ['Disk Free',       $health['disk_free'],      'kc-badge-silver'],
      ['Last GL Post',    $health['last_gl_post'],   'kc-badge-silver'],
      ['Current Period',  $health['current_period_status'], match($health['current_period_status']??'open') {'Locked'=>'kc-badge-silver','Closed'=>'kc-badge-navy','Closing'=>'kc-badge-gold',default=>'kc-badge-green'}],
    ] as [$label, $value, $cls])
    <div class="kc-stat-card text-center">
      <p class="text-[10px] text-kc-charcoal/70 uppercase tracking-wider">{{ $label }}</p>
      <p class="font-semibold text-sm text-kc-navy mt-1">{{ $value }}</p>
    </div>
    @endforeach
  </div>

  {{-- Error count cards --}}
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="kc-stat-card text-center {{ $counts['error'] > 0 ? 'border border-red-200' : '' }}">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Errors Today</p>
      <p class="font-display text-2xl font-bold {{ $counts['error'] > 0 ? 'text-red-600' : 'text-emerald-600' }} mt-1">{{ $counts['error'] }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Warnings Today</p>
      <p class="font-display text-2xl font-bold text-orange-500 mt-1">{{ $counts['warning'] }}</p>
    </div>
    <div class="kc-stat-card text-center">
      <p class="text-xs text-kc-charcoal/70 uppercase tracking-wider">Info Today</p>
      <p class="font-display text-2xl font-bold text-kc-charcoal/70 mt-1">{{ $counts['info'] }}</p>
    </div>
  </div>

  {{-- Scheduled Jobs — manual "run now" for jobs that otherwise only run
       via cron and have no other admin trigger. --}}
  <div class="kc-card mb-6">
    <h4 class="font-display font-semibold text-kc-navy mb-3">Scheduled Jobs</h4>
    <div class="space-y-2">
      <div class="flex items-center justify-between p-3 rounded-lg border border-kc-silver-light">
        <div>
          <p class="text-sm font-semibold">Escalate Arrears</p>
          <p class="text-xs text-kc-charcoal/70">Sends overdue-account escalation emails by DPD tier. Runs daily at 09:00 via cron.</p>
        </div>
        <form method="POST" action="{{ route('admin.system.jobs.run') }}"
          onsubmit="return confirm('This sends real escalation emails to every overdue client right now. Continue?')">
          @csrf
          <input type="hidden" name="job" value="escalate-arrears">
          <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Run Now</button>
        </form>
      </div>
      <div class="flex items-center justify-between p-3 rounded-lg border border-kc-silver-light">
        <div>
          <p class="text-sm font-semibold">Send Payment Reminders</p>
          <p class="text-xs text-kc-charcoal/70">Sends reminder emails for instalments due soon (per Lending Settings). Runs daily at 08:00 via cron.</p>
        </div>
        <form method="POST" action="{{ route('admin.system.jobs.run') }}"
          onsubmit="return confirm('This sends real reminder emails to clients right now. Continue?')">
          @csrf
          <input type="hidden" name="job" value="send-payment-reminders">
          <button type="submit" class="kc-btn-ghost text-xs py-1 px-3">Run Now</button>
        </form>
      </div>
    </div>
  </div>

  {{-- Filters + actions --}}
  <div class="flex flex-wrap gap-3 mb-4 items-end">
    <form method="GET" class="flex flex-wrap gap-3">
      <div>
        <label class="kc-label">Level</label>
        <select name="level" class="kc-select">
          <option value="all" {{ $level==='all'?'selected':'' }}>All Levels</option>
          @foreach(['error','warning','info','debug'] as $l)
          <option value="{{ $l }}" {{ $level===$l?'selected':'' }}>{{ strtoupper($l) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="kc-label">Date</label>
        <input type="date" name="date" value="{{ $date }}" class="kc-input">
      </div>
      <div>
        <label class="kc-label">Search</label>
        <input type="text" name="search" value="{{ $search }}" class="kc-input" placeholder="Search messages...">
      </div>
      <div class="flex items-end">
        <button type="submit" class="kc-btn-primary">Filter</button>
      </div>
    </form>

    <div class="flex gap-2 ml-auto">
      <a href="{{ route('admin.system.logs.download') }}" class="kc-btn-ghost text-sm">Download Log</a>
      <form method="POST" action="{{ route('admin.system.logs.clear') }}">
        @csrf
        <button type="submit" class="kc-btn-ghost text-sm text-red-600 border-red-200"
          onclick="return confirm('Archive and clear the log file?')">Clear Log</button>
      </form>
    </div>
  </div>

  {{-- Failed jobs --}}
  @if(!empty($failedJobs))
  <div class="kc-card mb-5 border-l-4 border-red-400">
    <div class="flex items-center justify-between mb-3">
      <h4 class="font-display font-semibold text-kc-navy">Failed Queue Jobs ({{ count($failedJobs) }})</h4>
      <form method="POST" action="{{ route('admin.system.logs.clear-failed') }}">
        @csrf
        <button type="submit" class="text-xs text-red-600 hover:underline"
          onclick="return confirm('Clear all failed jobs?')">Clear All</button>
      </form>
    </div>
    <div class="kc-table-scroll">
      <table class="kc-table">
        <thead><tr><th>Job</th><th>Queue</th><th>Failed At</th><th>Error</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($failedJobs as $job)
          <tr>
            <td data-label="Job" class="font-mono text-xs">{{ $job['display_name'] }}</td>
            <td data-label="Queue" class="text-xs text-kc-charcoal/70">{{ $job['queue'] }}</td>
            <td data-label="Failed" class="text-xs text-kc-charcoal/70">{{ \Carbon\Carbon::parse($job['failed_at'])->format('d M Y H:i') }}</td>
            <td data-label="Error" class="text-xs text-red-600 max-w-xs truncate">{{ $job['exception'] }}</td>
            <td data-label="Action">
              <form method="POST" action="{{ route('admin.system.logs.retry') }}" class="inline">
                @csrf
                <input type="hidden" name="uuid" value="{{ $job['uuid'] }}">
                <button type="submit" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Retry</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Log entries --}}
  <div class="kc-card">
    <div class="flex items-center justify-between mb-4">
      <h4 class="font-display font-semibold text-kc-navy">Log Entries (most recent first)</h4>
      <span class="text-xs text-kc-charcoal/70">Showing up to 500 lines from today</span>
    </div>

    @if(empty($entries))
    <p class="text-center text-kc-charcoal/70 py-8">
      @if($level !== 'all' || $search)
        No entries match your filter.
      @else
        No log entries for {{ $date }}.
      @endif
    </p>
    @else
    <div class="space-y-2">
      @foreach($entries as $entry)
      @php
        $levelClass = match($entry['level']) {
          'ERROR'    => 'border-red-300 bg-red-50',
          'WARNING'  => 'border-orange-300 bg-orange-50',
          'CRITICAL' => 'border-red-500 bg-red-100',
          'INFO'     => 'border-kc-silver-light bg-white',
          default    => 'border-kc-silver-light bg-white',
        };
        $badgeClass = match($entry['level']) {
          'ERROR','CRITICAL' => 'kc-badge-red',
          'WARNING'          => 'kc-badge-gold',
          'INFO'             => 'kc-badge-navy',
          default            => 'kc-badge-silver',
        };
      @endphp
      <div x-data="{open:false}" class="border rounded-lg {{ $levelClass }} overflow-hidden">
        <button @click="open=!open" class="w-full flex items-start gap-3 p-3 text-left hover:bg-black/5 transition">
          <span class="kc-badge {{ $badgeClass }} flex-shrink-0 text-[10px] mt-0.5">{{ $entry['level'] }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-mono text-kc-charcoal/70 flex-shrink-0 mr-2">{{ $entry['timestamp'] }}</p>
            <p class="text-sm text-kc-charcoal truncate">{{ $entry['message'] }}</p>
          </div>
          <svg :class="open?'rotate-90':''" class="w-4 h-4 text-kc-charcoal/70 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <div x-show="open" x-transition class="border-t border-kc-silver-light p-3 bg-white max-w-full overflow-hidden">
          <div class="mb-2">
            <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">Full message:</p>
            <p class="text-xs text-kc-charcoal break-words whitespace-pre-wrap">{{ $entry['message'] }}</p>
          </div>
          @if($entry['sql'] ?? null)
          <div class="mb-2">
            <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">SQL query:</p>
            <pre class="text-xs bg-kc-charcoal text-kc-gold p-2 rounded max-w-full whitespace-pre-wrap break-words">{{ $entry['sql'] }}</pre>
          </div>
          @endif
          @if($entry['context'])
          <div class="mb-2">
            <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">Context:</p>
            <pre class="text-xs bg-kc-charcoal text-emerald-400 p-2 rounded max-w-full whitespace-pre-wrap break-words">{{ json_encode($entry['context'], JSON_PRETTY_PRINT) }}</pre>
          </div>
          @endif
          @if(!empty($entry['extra']))
          <div>
            <p class="text-xs font-semibold text-kc-charcoal/70 mb-1">Stack trace:</p>
            <pre class="text-xs bg-kc-charcoal text-white/60 p-2 rounded max-w-full max-h-48 overflow-y-auto whitespace-pre-wrap break-words">{{ implode("\n", array_slice($entry['extra'], 0, 20)) }}</pre>
          </div>
          @endif
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>
</x-app-layout>
