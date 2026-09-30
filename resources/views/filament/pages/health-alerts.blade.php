<x-filament-panels::page>
    @php($alerts = $this->alerts)
    @forelse ($alerts as $alert)
        <div @class([
            'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
            'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' => $alert['severity'] === 'danger',
            'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950' => $alert['severity'] === 'warning',
            'border-gray-200 dark:border-white/10' => $alert['severity'] === 'info',
        ])>
            <span class="font-medium capitalize">{{ str_replace('_', ' ', $alert['type']) }}</span>
            <span class="flex-1">{{ $alert['message'] }}</span>
            @if ($alert['animal_id'])
                <a class="text-primary-600 hover:underline" href="{{ $this->animalUrl($alert['animal_id']) }}">Open animal</a>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Nothing needs attention right now.</p>
    @endforelse
</x-filament-panels::page>
