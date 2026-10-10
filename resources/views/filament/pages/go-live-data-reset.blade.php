<x-filament-panels::page>
    @php($mode = $this->resetter->mode())
    @php($preview = $this->preview)

    <x-filament::section heading="Data mode" description="The reset only works while the system holds practice data. Once it has run, the mode becomes Live and the reset switches itself off.">
        <div class="flex items-center gap-3 text-sm">
            <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $mode->value === 'demo' ? 'bg-warning-100 text-warning-700 dark:bg-warning-950 dark:text-warning-300' : 'bg-success-100 text-success-700 dark:bg-success-950 dark:text-success-300' }}">{{ $mode->label() }}</span>
            @if ($mode->value === 'live')
                <span>This system is in live mode. Practice data can no longer be cleared from here. To clear data on a test copy, set <em>Data mode</em> to <strong>demo</strong> in Farm settings.</span>
            @else
                <span>Everything below is practice data and will be deleted when you press <strong>Clear practice data</strong>.</span>
            @endif
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Will be kept" description="Set-up that real work needs.">
            <ul class="list-disc space-y-1 pl-5 text-sm">
                <li>Users, roles and permissions (and your own login)</li>
                <li>The farm, its production units and every farm setting</li>
                <li>Lists: animal categories, causes, customer types and the other lookup values; units of measure; breeds; feed types</li>
                <li>Chart of accounts and cost centres</li>
                <li>Backup history</li>
                <li>The standard stores, semen and meat stock items and meat products are put back</li>
            </ul>
        </x-filament::section>

        <x-filament::section heading="Will be deleted" description="{{ number_format(array_sum($preview)) }} records in {{ count($preview) }} tables.">
            @if ($preview === [])
                <p class="text-sm text-gray-500">There is nothing to clear.</p>
            @else
                <div class="azana-report max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sr-only"><tr><th scope="col">Table</th><th scope="col">Records</th></tr></thead>
                        <tbody>
                            @foreach ($preview as $table => $count)
                                <tr class="border-t border-gray-200 first:border-0 dark:border-white/10">
                                    <td class="py-1 pr-4">{{ str($table)->replace('_', ' ')->ucfirst() }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
