<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Edit {{ $recipient->name }}</span>
    <p class="kc-page-subtitle">Slug: {{ $recipient->slug }}</p>
  </x-slot>

  <div class="kc-card max-w-3xl">
    @include('admin.exports.recipients._form', [
      'recipient' => $recipient,
      'action' => route('admin.exports.recipients.update', $recipient),
      'method' => 'PUT',
    ])
  </div>
</x-app-layout>
