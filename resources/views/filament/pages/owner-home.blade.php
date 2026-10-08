<x-filament-panels::page>
    @php
        $s = $this->snapshot;
    @endphp

    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($this->links() as $link)
            <a href="{{ $link['url'] }}"
                class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>

    @if (collect($s['attention'])->contains(fn ($n) => $n !== null))
        <x-filament::section
            :icon="Filament\Support\Icons\Heroicon::BellAlert"
            :heading="__('Needs attention')"
        >
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['critical_alerts' => 'Critical alerts', 'overdue_tasks' => 'Overdue tasks', 'my_open_tasks' => 'My open tasks', 'approvals' => 'Waiting for approval'] as $key => $label)
                    @if ($s['attention'][$key] !== null)
                        <div
                            class="flex items-center gap-3 rounded-lg border p-4 {{ $key === 'critical_alerts'
                                ? 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950'
                                : ($key === 'overdue_tasks' && $s['attention'][$key] > 0
                                    ? 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950'
                                    : 'border-gray-200 dark:border-white/10 bg-white dark:bg-gray-950'
                                )
                            }}"
                        >
                            <div class="flex-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $s['attention'][$key] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @foreach (['today' => 'Today', 'now' => 'The farm now'] as $block => $title)
        @php
            $icon = $block === 'today' ? Filament\Support\Icons\Heroicon::CalendarDays : Filament\Support\Icons\Heroicon::LightBulb;
        @endphp
        <x-filament::section
            :icon="$icon"
            :heading="__('The farm ' . $title)"
        >
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($s[$block] as $k)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $k['label'] }}</div>
                            <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                                {{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
    @endforeach

    @if ($s['month'])
        <x-filament::section
            :icon="Filament\Support\Icons\Heroicon::ChartBar"
            :heading="__('This month against target')"
        >
            @include('filament.pages.partials.kpi-table', ['kpis' => $s['month']])
        </x-filament::section>
    @endif
</x-filament-panels::page>
