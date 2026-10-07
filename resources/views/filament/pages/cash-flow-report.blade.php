<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">From
            <input type="date" wire:model.live="from" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
        <label class="flex flex-col gap-1">To
            <input type="date" wire:model.live="to" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
        </label>
    </div>

    @foreach ($this->inputErrors() as $error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endforeach

    @if ($report = $this->report)
        <p class="text-sm">Opening balance {{ \App\Filament\Support\MoneyColumn::format($report['opening_minor']) }}; in {{ \App\Filament\Support\MoneyColumn::format($report['in_minor']) }}, out {{ \App\Filament\Support\MoneyColumn::format($report['out_minor']) }}, net {{ \App\Filament\Support\MoneyColumn::format($report['net_minor']) }}; closing balance {{ \App\Filament\Support\MoneyColumn::format($report['closing_minor']) }}.</p>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach (['inflows' => 'Money in', 'outflows' => 'Money out'] as $key => $title)
                <section class="space-y-2">
                    <h2 class="text-base font-semibold">{{ $title }}</h2>
                    @forelse ($report[$key] as $purpose => $amount)
                        <div class="flex justify-between rounded-lg border border-gray-200 px-4 py-2 text-sm dark:border-white/10"><span>{{ $purpose }}</span><span>{{ \App\Filament\Support\MoneyColumn::format($amount) }}</span></div>
                    @empty
                        <p class="text-sm text-gray-500">Nothing in this period.</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
