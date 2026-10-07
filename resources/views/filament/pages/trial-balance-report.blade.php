<x-filament-panels::page>
    @php($report = $this->report)
    <p class="text-sm">Debits {{ \App\Filament\Support\MoneyColumn::format($report['debit_minor']) }}, credits {{ \App\Filament\Support\MoneyColumn::format($report['credit_minor']) }}: {{ $report['balanced'] ? 'the books balance.' : 'THE BOOKS DO NOT BALANCE.' }}</p>
    @if ($report['rows'])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-2">Account</th><th class="px-4 py-2">Type</th><th class="px-4 py-2 text-right">Debits</th><th class="px-4 py-2 text-right">Credits</th><th class="px-4 py-2 text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['rows'] as $row)
                        <tr><td class="px-4 py-2">{{ $row['account']->label() }}</td><td class="px-4 py-2">{{ $row['account']->type->label() }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['debit_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['credit_minor']) }}</td><td class="px-4 py-2 text-right">{{ \App\Filament\Support\MoneyColumn::format($row['balance_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Nothing has been posted yet.</p>
    @endif
</x-filament-panels::page>
