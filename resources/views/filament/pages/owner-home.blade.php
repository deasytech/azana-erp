<x-filament-panels::page>
    @php
        $s = $this->snapshot;
        $fmt = fn (array $k) => \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']);
        $now = collect($s['now'])->keyBy('key');
        $headline = $now->only(['herd.active_animals', 'production.growing_pigs', 'finance.cash_minor', 'sales.receivables_minor']);
        $levels = $now->except($headline->keys()->all());
        $attention = $this->attention();
        $anyAttention = collect($attention)->contains(fn ($a) => $a['count'] > 0);
    @endphp

    {{-- Welcome and the way into the rest --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $this->greeting() }}, {{ auth()->user()->name }}. {{ now()->format('l, j F Y') }}.
        </p>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->links() as $link)
                <x-filament::button tag="a" :href="$link['url']" color="gray" size="sm" outlined>
                    {{ $link['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </div>

    {{-- What needs attention: only items this user may act on --}}
    @if (count($attention))
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::BellAlert" :heading="__('Needs attention')"
            :description="$anyAttention ? __('Open these first.') : __('Nothing is waiting for you right now.')">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($attention as $a)
                    <x-erp.kpi :label="$a['label']" :value="$a['count']" :url="$a['url']"
                        :tone="$a['count'] > 0 ? $a['tone'] : null"
                        :hint="$a['count'] > 0 ? $a['hint'] : null" />
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- The farm now: headline figures first, the other levels beneath --}}
    @if ($now->isNotEmpty())
        <section class="space-y-3" aria-labelledby="farm-now">
            <h2 id="farm-now" class="text-base font-semibold text-gray-950 dark:text-white">{{ __('The farm now') }}</h2>
            @if ($headline->isNotEmpty())
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($headline as $k)
                        <x-erp.kpi :label="$k['label']" :value="$fmt($k)" primary />
                    @endforeach
                </div>
            @endif
            @if ($levels->isNotEmpty())
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($levels as $k)
                        <x-erp.kpi :label="$k['label']" :value="$fmt($k)" />
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Today --}}
    @if (count($s['today']))
        <section class="space-y-3" aria-labelledby="farm-today">
            <h2 id="farm-today" class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Today') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($s['today'] as $k)
                    <x-erp.kpi :label="$k['label']" :value="$fmt($k)" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- This month against target --}}
    @if ($s['month'])
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::ChartBar" :heading="__('This month against target')"
            :description="__('The headline indicators for :month.', ['month' => now()->format('F Y')])">
            @include('filament.pages.partials.kpi-table', ['kpis' => $s['month']])
        </x-filament::section>
    @endif
</x-filament-panels::page>
