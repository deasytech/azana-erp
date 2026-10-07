@php($labels = ['met' => 'Met', 'missed' => 'Missed', 'none' => ''])
<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
        <thead class="bg-gray-50 text-left dark:bg-white/5">
            <tr><th class="px-4 py-2">Indicator</th><th class="px-4 py-2 text-right">Actual</th><th class="px-4 py-2 text-right">Target</th><th class="px-4 py-2 text-right">Reached</th><th class="px-4 py-2">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($kpis as $k)
                <tr>
                    <td class="px-4 py-2">{{ $k['label'] }}</td>
                    <td class="px-4 py-2 text-right">{{ \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['value']) }}</td>
                    <td class="px-4 py-2 text-right">{{ $k['target'] !== null ? \App\Domain\Reporting\KpiRegistry::format($k['unit'], $k['target']) : '-' }}</td>
                    <td class="px-4 py-2 text-right">{{ $k['attainment_percent'] !== null ? $k['attainment_percent'].'%' : '-' }}</td>
                    <td @class(['px-4 py-2', 'text-success-600' => $k['status'] === 'met', 'text-danger-600' => $k['status'] === 'missed'])>{{ $labels[$k['status']] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
