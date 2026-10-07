<x-filament-panels::page>
    @php($s = $this->snapshot)
    @php($units = $s['units'])
    @php($tile = fn ($k) => $k)
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($this->links() as $link)
            <a href="{{ $link['url'] }}" class="rounded-lg border border-gray-300 px-3 py-1 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">{{ $link['label'] }}</a>
        @endforeach
    </div>

    @if (collect($s['attention'])->contains(fn ($n) => $n !== null))
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Needs attention</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['critical_alerts' => 'Critical alerts', 'overdue_tasks' => 'Overdue tasks', 'my_open_tasks' => 'My open tasks', 'approvals' => 'Waiting for approval'] as $key => $label)
                    @if ($s['attention'][$key] !== null)
                        <div @class(['rounded-lg border p-4', 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' => in_array($key, ['critical_alerts', 'overdue_tasks'], true) && $s['attention'][$key] > 0, 'border-gray-200 dark:border-white/10' => ! (in_array($key, ['critical_alerts', 'overdue_tasks'], true) && $s['attention'][$key] > 0)])>
                            <div class="text-xs text-gray-500">{{ $label }}</div><div class="text-xl font-semibold">{{ $s['attention'][$key] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @foreach (['today' => 'Today', 'now' => 'The farm now'] as $block => $title)
        @if ($s[$block])
            <section class="space-y-2">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($s[$block] as $k)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs text-gray-500">{{ $k['label'] }}</div>
                            <div class="text-xl font-semibold">{{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($s['month'])
        <section class="space-y-2">
            <h2 class="text-base font-semibold">This month against target</h2>
            @include('filament.pages.partials.kpi-table', ['kpis' => $s['month']])
        </section>
    @endif
</x-filament-panels::page>
