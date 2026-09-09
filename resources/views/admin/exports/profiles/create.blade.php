<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">New Export Profile</span>
    <p class="kc-page-subtitle">Pick columns, order, format and schedule for a recipient</p>
  </x-slot>

  <div class="kc-card max-w-4xl">
    @include('admin.exports.profiles._form', [
      'profile' => new \App\Models\ExportProfile,
      'action' => route('admin.exports.profiles.store'),
      'method' => 'POST',
    ])
  </div>
</x-app-layout>
