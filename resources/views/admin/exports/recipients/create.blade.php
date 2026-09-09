<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">New Export Recipient</span>
    <p class="kc-page-subtitle">Define where an outbound feed is sent</p>
  </x-slot>

  <div class="kc-card max-w-3xl">
    @include('admin.exports.recipients._form', [
      'recipient' => new \App\Models\ExportRecipient(['kind' => 'bureau', 'transport' => 'download']),
      'action' => route('admin.exports.recipients.store'),
      'method' => 'POST',
    ])
  </div>
</x-app-layout>
