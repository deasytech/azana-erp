<x-filament-panels::page>
    @php($alerts = $this->alerts)
    @forelse ($alerts->groupBy('area') as $area => $group)
        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ $area }}</h2>
            @foreach ($group as $alert)
                <div @class([
                    'rounded-lg border px-4 py-3 text-sm',
                    'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' => $alert['severity'] === 'danger',
                    'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950' => $alert['severity'] === 'warning',
                    'border-gray-200 dark:border-white/10' => $alert['severity'] === 'info',
                ])>{{ $alert['message'] }}</div>
            @endforeach
        </section>
    @empty
        <p class="text-sm text-gray-500">Nothing needs attention right now.</p>
    @endforelse
</x-filament-panels::page>
