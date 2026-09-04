<x-app-layout>
  <x-slot name="header">
    <span class="kc-page-title">Agreements</span>
    <p class="kc-page-subtitle">Application #{{ str_pad($application->id,6,'0',STR_PAD_LEFT) }}</p>
  </x-slot>

  <div class="kc-card">
    @if($agreements->isEmpty())
    <p class="text-center text-kc-charcoal/60 py-8">No NCA documents generated yet.</p>
    @else
    <div class="space-y-3">
      @foreach($agreements as $ag)
      <div class="flex items-center justify-between p-4 rounded-xl border border-kc-silver-light hover:border-kc-gold/30 transition">
        <div>
          <p class="font-semibold text-kc-navy">{{ $ag->getTypeLabel() }}</p>
          <p class="text-xs text-kc-charcoal/60 font-mono">{{ $ag->reference }}</p>
          <p class="text-xs text-kc-charcoal/60 mt-1">Generated {{ $ag->generated_at?->format('d M Y H:i') }}</p>
          @if($ag->accepted_at)
            <span class="kc-badge kc-badge-green text-[10px] mt-1">Signed {{ $ag->accepted_at->format('d M Y') }}</span>
          @endif
        </div>
        <div class="flex items-center gap-3">
          @if(!$ag->accepted_at && $ag->user_id === auth()->id())
          <form method="POST" action="{{ route('agreements.accept', $ag) }}">
            @csrf
            <button type="submit" class="kc-btn-primary text-xs py-1 px-3">Sign</button>
          </form>
          @endif
          <a href="{{ route('agreements.download', $ag) }}" target="_blank" class="kc-btn-ghost text-xs py-1 px-3">PDF</a>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>
</x-app-layout>
