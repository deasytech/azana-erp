<x-filament-panels::page class="azana-report">
    <x-erp.filters heading="Period" description="Breeding events expected between these dates.">
        <x-erp.filter-date label="From" wire:model.live="from" />
        <x-erp.filter-date label="To" wire:model.live="to" />
        <x-slot:aside>
            <span class="text-xs text-gray-500 dark:text-gray-400">Quick range</span>
            @foreach ([7 => 'Next 7 days', 30 => 'Next 30 days', 90 => 'Next 90 days'] as $days => $label)
                <x-filament::button color="gray" size="sm" outlined wire:click="range({{ $days }})">{{ $label }}</x-filament::button>
            @endforeach
        </x-slot:aside>
    </x-erp.filters>

    @if ($error = $this->inputError())
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @else
        @php
            $entries = $this->entries;
            $byType = $entries->countBy('type');
        @endphp

        @if ($entries->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (['Pregnancy check due', 'Watch for return to heat', 'Farrowing expected', 'Weaning due', 'Next service due'] as $type)
                    <x-erp.kpi :label="$type" :value="$byType[$type] ?? 0" />
                @endforeach
            </div>
        @endif

        <div class="space-y-4">
            @forelse ($entries->groupBy(fn ($e) => $e['date']->toDateString()) as $date => $day)
                @php
                    $d = \Carbon\Carbon::parse($date);
                    $away = (int) now()->startOfDay()->diffInDays($d, false);
                    $relative = match (true) {
                        $away === 0 => 'Today',
                        $away === 1 => 'Tomorrow',
                        $away < 0 => abs($away).' days ago',
                        default => 'In '.$away.' days',
                    };
                @endphp
                <section class="azana-day">
                    <div class="azana-day-date">
                        <span class="azana-day-number">{{ $d->format('d') }}</span>
                        <span class="azana-day-month">{{ $d->format('M Y') }}</span>
                        <span class="azana-day-weekday">{{ $d->format('l') }}</span>
                        <span @class(['azana-day-relative', 'azana-day-today' => $away === 0])>{{ $relative }}</span>
                    </div>
                    <ul class="azana-day-events">
                        @foreach ($day as $entry)
                            @php($style = \App\Filament\Pages\BreedingCalendar::style($entry['type']))
                            <li>
                                <x-filament::badge :color="$style['color']" :icon="$style['icon']" class="w-fit sm:w-56">{{ $entry['type'] }}</x-filament::badge>
                                <a class="font-medium text-primary-600 hover:underline" href="{{ $this->animalUrl($entry['sow_id']) }}">{{ $entry['sow'] }}</a>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $entry['detail'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-white/10">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" class="h-8 w-8 text-gray-400" />
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Nothing is expected in this period.</p>
                    <p class="text-xs text-gray-500">Try a longer range, or record services so checks, farrowings and weanings appear here.</p>
                </div>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
