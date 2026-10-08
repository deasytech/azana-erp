<x-filament-panels::page>
    <form wire:submit.prevent>
    <x-erp.filters heading="Find a record" description="Pick what to trace and enter its reference.">
        <x-erp.filter-select label="Trace a" wire:model.live="subject">
                @foreach (\App\Filament\Pages\TraceExplorer::SUBJECTS as $value => $label)
                    <option value="{{ $value }}">{{ str_replace(' number', '', $label) }}</option>
                @endforeach
            
        </x-erp.filter-select>
        <x-erp.filter-text :label="\App\Filament\Pages\TraceExplorer::SUBJECTS[$subject] ?? 'Reference'" wire:model.live.debounce.400ms="reference" placeholder="e.g. IPA-MT-20261008-001" />
    </x-erp.filters>
    </form>

    @php($result = $this->result())

    @if ($result['error'])
        <p class="text-sm text-danger-600">{{ $result['error'] }}</p>
    @elseif ($trace = $result['trace'])
        @php($subjectKey = $trace['subject']['key'])
        <p class="text-sm">
            Tracing <strong>{{ $trace['subject']['label'] }}</strong>. <span class="text-gray-500">Everything that led to it is marked <em>before</em>; everything that came of it, <em>after</em>.</span>
        </p>

        <div class="space-y-4">
            @foreach ($trace['stages'] as $stage)
                <section class="space-y-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $stage['title'] }}</h2>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($stage['nodes'] as $node)
                            @php($url = $this->link($node['type'], $node['id']))
                            @php($role = $node['key'] === $subjectKey ? 'this' : (in_array($node['key'], $trace['upstream'], true) ? 'before' : (in_array($node['key'], $trace['downstream'], true) ? 'after' : 'related')))
                            <div @class([
                                'rounded-lg border px-3 py-2 text-sm',
                                'border-primary-400 bg-primary-50 dark:border-primary-600 dark:bg-primary-950' => $role === 'this',
                                'border-gray-200 dark:border-white/10' => $role !== 'this',
                            ])>
                                <div class="flex items-center justify-between gap-2">
                                    @if ($url)
                                        <a class="font-medium text-primary-600 hover:underline" href="{{ $url }}">{{ $node['label'] }}</a>
                                    @else
                                        <span class="font-medium">{{ $node['label'] }}</span>
                                    @endif
                                    <span class="text-xs text-gray-500">{{ $role === 'this' ? 'this' : $role }}</span>
                                </div>
                                @if ($node['detail'])
                                    <div class="text-xs text-gray-500">{{ $node['detail'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if ($trace['edges'])
            <details class="text-sm">
                <summary class="cursor-pointer font-medium">How it connects ({{ count($trace['edges']) }} links)</summary>
                <ul class="mt-2 space-y-1 text-gray-600 dark:text-gray-300">
                    @php($labels = collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->pluck('label', 'key'))
                    @foreach ($trace['edges'] as $edge)
                        <li>{{ $labels[$edge['from']] ?? $edge['from'] }} &rarr; {{ $labels[$edge['to']] ?? $edge['to'] }}@if ($edge['label']) <span class="text-gray-400">({{ $edge['label'] }})</span>@endif</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @else
        <p class="text-sm text-gray-500">Enter a number to see where it came from and where it went.</p>
    @endif
</x-filament-panels::page>
