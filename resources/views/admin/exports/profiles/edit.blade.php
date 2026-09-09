<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Edit {{ $profile->name }}</span>
    <p class="kc-page-subtitle">Recipient: {{ $profile->recipient?->name ?? '—' }}</p>
  </x-slot>

  <div class="kc-card max-w-4xl">
    @include('admin.exports.profiles._form', [
      'profile' => $profile,
      'action' => route('admin.exports.profiles.update', $profile),
      'method' => 'PUT',
    ])
  </div>
</x-app-layout>
