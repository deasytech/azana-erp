<x-filament-panels::page>
    @php($report = $this->report)
    @foreach (['receivables' => 'Owed by customers', 'payables' => 'Owed to suppliers'] as $key => $title)
        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ $title }}: {{ \App\Filament\Support\MoneyColumn::format($report[$key]['total_minor']) }}</h2>
            @if ($report[$key]['parties'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2 text-right">Not yet due</th><th class="px-4 py-2 text-right">1-30 days</th><th class="px-4 py-2 text-right">31-60 days</th><th class="px-4 py-2 text-right">Over 60 days</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report[$key]['parties'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['name'] }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['current']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['1_30']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['31_60']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['over_60']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['total_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
            @else
                <p class="text-sm text-gray-500">Nothing outstanding.</p>
            @endif
        </section>
    @endforeach
</x-filament-panels::page>
