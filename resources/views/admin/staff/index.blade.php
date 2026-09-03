<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Staff Management</span>
    <p class="kc-page-subtitle">Invite new staff, or change an existing member's role</p>
  </x-slot>

  @if(session('success'))
    <div class="kc-alert-success mb-5">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="kc-alert-error mb-5">{{ session('error') }}</div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- LEFT: Current staff --}}
    <div class="lg:col-span-2">
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Current Staff</h4>
        <div class="kc-table-scroll">
          <table class="kc-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead>
            <tbody>
              @forelse($staff as $member)
              <tr>
                <td data-label="Name" class="font-semibold">{{ $member->name }}</td>
                <td data-label="Email" class="text-xs text-kc-charcoal/60">{{ $member->email }}</td>
                <td data-label="Role">
                  <form method="POST" action="{{ route('admin.staff.update-role', $member) }}" class="flex items-center gap-2">
                    @csrf @method('PUT')
                    <select name="system_role" class="kc-select !py-1 !text-xs"
                            data-current="{{ $member->system_role }}"
                            onchange="if (confirm('Change ' + @js($member->name) + ' to ' + this.options[this.selectedIndex].text.trim() + '?')) { this.form.submit(); } else { this.value = this.dataset.current; }">
                      <option value="loan_officer" {{ $member->system_role==='loan_officer'?'selected':'' }}>Loan Officer</option>
                      <option value="finance" {{ $member->system_role==='finance'?'selected':'' }}>Finance</option>
                      <option value="admin" {{ $member->system_role==='admin'?'selected':'' }}>Admin</option>
                      <option value="it_admin" {{ $member->system_role==='it_admin'?'selected':'' }}>IT Admin</option>
                      <option value="viewer" {{ $member->system_role==='viewer'?'selected':'' }}>Viewer</option>
                      <option value="client" {{ $member->system_role==='client'?'selected':'' }}>— Demote to Client</option>
                    </select>
                  </form>
                </td>
                <td data-label="Action" class="text-xs text-kc-charcoal/60">
                  {{ $member->id === Auth::id() ? 'You' : '' }}
                </td>
              </tr>
              @empty
              <tr><td colspan="4" class="text-center py-8 text-kc-charcoal/60">No staff accounts yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- RIGHT: Invite new / promote existing --}}
    <div class="space-y-4">
      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Invite New Staff Member</h4>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-4">
          @csrf
          <div>
            <label class="kc-label">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="kc-input @error('name') border-red-400 @enderror">
            @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="kc-input @error('email') border-red-400 @enderror">
            @error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="kc-label">Role</label>
            <select name="system_role" class="kc-select" required>
              @foreach($roles as $role)
                <option value="{{ $role }}" {{ old('system_role')===$role?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$role)) }}</option>
              @endforeach
            </select>
            @error('system_role')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
          </div>
          <p class="text-[11px] text-kc-charcoal/60">They'll receive an email with a link to set their own password.</p>
          <button type="submit" class="kc-btn-primary w-full justify-center">Send Invite</button>
        </form>
      </div>

      <div class="kc-card">
        <h4 class="font-display font-semibold text-kc-navy mb-4">Promote Existing Client</h4>
        <form method="GET" action="{{ route('admin.staff.index') }}" class="flex gap-2 mb-4">
          <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email" class="kc-input">
          <button type="submit" class="kc-btn-ghost">Search</button>
        </form>

        @if($search)
          @forelse($searchResults as $client)
          <div class="flex items-center justify-between py-2 border-b border-kc-silver-light/60 last:border-0">
            <div class="min-w-0">
              <p class="text-xs font-semibold text-kc-navy truncate">{{ $client->name }}</p>
              <p class="text-[11px] text-kc-charcoal/50 truncate">{{ $client->email }}</p>
            </div>
            <form method="POST" action="{{ route('admin.staff.update-role', $client) }}" class="flex items-center gap-1 flex-shrink-0">
              @csrf @method('PUT')
              <select name="system_role" class="kc-select !py-1 !text-xs">
                @foreach($roles as $role)
                  <option value="{{ $role }}">{{ ucfirst(str_replace('_',' ',$role)) }}</option>
                @endforeach
              </select>
              <button type="submit" class="text-xs text-kc-navy underline underline-offset-2 hover:text-kc-gold-muted">Promote</button>
            </form>
          </div>
          @empty
          <p class="text-xs text-kc-charcoal/60">No matching clients found.</p>
          @endforelse
        @endif
      </div>
    </div>
  </div>
</x-app-layout>
