<x-filament-panels::page>
    @php($checks = $this->checks)
    @php($tone = ['ok' => 'border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-950', 'warning' => 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950', 'failed' => 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950'])
    @php($word = ['ok' => 'OK', 'warning' => 'Check', 'failed' => 'Failed'])

    <x-filament::section heading="System status" description="The checks the monitor runs every fifteen minutes. A failed check alerts the people who look after the system.">
        <div class="space-y-2">
            @foreach ($checks as $check)
                <div class="rounded-lg border px-4 py-3 text-sm {{ $tone[$check->state] }}">
                    <span class="font-semibold">{{ $check->name }}</span>
                    <span class="ml-2 text-xs uppercase tracking-wide">{{ $word[$check->state] }}</span>
                    <div>{{ $check->detail }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Backup history" description="Every backup and restore test, the latest first. Old backups are removed by the retention rule; their rows stay.">
        <div class="azana-report overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="py-2 pr-4">When</th><th class="pr-4">What</th><th class="pr-4">Result</th><th class="pr-4">Size</th><th class="pr-4">Off-site</th><th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->runs as $run)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $run->started_at->format('d M Y H:i') }}</td>
                            <td class="pr-4">{{ $run->kind === 'backup' ? 'Backup' : 'Restore test' }}</td>
                            <td class="pr-4 {{ $run->status === 'success' ? 'text-success-600' : 'text-danger-600' }}">{{ $run->status === 'success' ? 'Succeeded' : 'Failed' }}</td>
                            <td class="pr-4">{{ $run->size_bytes ? number_format($run->size_bytes / 1048576, 2).' MB' : '-' }}</td>
                            <td class="pr-4">{{ $run->kind === 'backup' ? ($run->offsite ? 'Yes' : 'No') : '-' }}</td>
                            <td>{{ $run->message }}{{ $run->pruned_at ? ' (file removed by retention)' : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-gray-500">No backup has been taken yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
