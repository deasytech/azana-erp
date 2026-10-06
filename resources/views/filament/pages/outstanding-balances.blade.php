<x-filament-panels::page>
    @php($rows = $this->balances)
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">No customer owes money or holds a deposit.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Customer</th><th class="px-4 py-2 text-right">Owes</th><th class="px-4 py-2 text-right">Overdue</th>
                        <th class="px-4 py-2 text-right">Deposit</th><th class="px-4 py-2 text-right">Credit limit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-2"><a class="text-primary-600 hover:underline" href="{{ $this->customerUrl($row['customer']->id) }}">{{ $row['customer']->name }}</a></td>
                            <td class="px-4 py-2 text-right font-medium">{{ $this->money($row['outstanding']) }}</td>
                            <td @class(['px-4 py-2 text-right', 'font-medium text-danger-600' => $row['overdue'] > 0])>{{ $this->money($row['overdue']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->money($row['deposit']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->money($row['credit_limit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
