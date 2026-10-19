<x-filament-panels::page class="azana-report">
    <x-erp.filters heading="Period" description="Choose how far back the reports look, then click a report.">
        <x-erp.filter-select label="Run reports for" wire:model.live="mode">
            <option value="glance">This week, this month and this year</option>
            <option value="custom">Dates I choose</option>
        </x-erp.filter-select>
        @if ($mode === 'custom')
            <x-erp.filter-date label="From" wire:model.live="from" />
            <x-erp.filter-date label="To" wire:model.live="to" />
        @endif
    </x-erp.filters>

    @foreach ($this->inputErrors() as $error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endforeach

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->reports as $key => $report)
            <div @class(['azana-report-card', 'azana-report-card-active' => $selected === $key])>
                <div class="flex items-start gap-3">
                    <x-filament::icon :icon="$report['icon']" class="h-6 w-6 shrink-0 text-primary-500" />
                    <div>
                        <h3 class="text-base font-semibold">{{ $report['title'] }}</h3>
                        <p class="text-sm text-gray-500">{{ $report['description'] }}</p>
                    </div>
                </div>
                <div class="mt-auto flex flex-wrap gap-2">
                    <x-filament::button size="sm" wire:click="show('{{ $key }}')">View</x-filament::button>
                    @if ($this->canExport())
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download('{{ $key }}', 'pdf')">PDF</x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download('{{ $key }}', 'xlsx')">Excel</x-filament::button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($document = $this->document)
        <section class="space-y-4">
            <h2 class="text-lg font-semibold">{{ $document->title }} <span class="text-sm font-normal text-gray-500">{{ $document->subtitle }}</span></h2>
            @foreach ($document->sections as $section)
                <div class="space-y-2">
                    <h3 class="text-base font-semibold">{{ $section['title'] }}</h3>
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 text-left dark:bg-white/5">
                                <tr>@foreach ($section['headings'] as $i => $heading)<th @class(['px-4 py-2', 'text-right' => $i > 0])>{{ $heading }}</th>@endforeach</tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @forelse ($section['rows'] as $row)
                                    <tr>@foreach ($row as $i => $cell)<td @class(['px-4 py-2', 'text-right' => $i > 0])>{{ $cell }}</td>@endforeach</tr>
                                @empty
                                    <tr><td class="px-4 py-2 text-gray-500" colspan="{{ count($section['headings']) }}">Nothing to show.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</x-filament-panels::page>
