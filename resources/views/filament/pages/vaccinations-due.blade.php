<x-filament-panels::page>
    @php($due = $this->due)
    @if ($due->isEmpty())
        <p class="text-sm text-gray-500">No vaccinations are due in the reminder window.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr><th class="px-4 py-2">Animal</th><th class="px-4 py-2">Vaccination</th><th class="px-4 py-2">Due</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($due as $row)
                        <tr>
                            <td class="px-4 py-2"><a class="text-primary-600 hover:underline" href="{{ $this->animalUrl($row['animal']->id) }}">{{ $row['animal']->animal_number }}</a></td>
                            <td class="px-4 py-2">{{ $row['schedule']->name }}</td>
                            <td class="px-4 py-2">{{ $row['due_on']->format('d M Y') }}</td>
                            <td class="px-4 py-2 font-medium {{ $row['overdue'] ? 'text-danger-600' : 'text-warning-600' }}">{{ $row['overdue'] ? 'Overdue' : 'Due soon' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
