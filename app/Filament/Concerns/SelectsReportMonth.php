<?php

namespace App\Filament\Concerns;

/** A year and month chosen on a report page. They are client-writable Livewire properties, so they are clamped to real values before use. */
trait SelectsReportMonth
{
    public int $year = 0;

    public int $month = 0;

    public function mountSelectsReportMonth(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    protected function chosenYear(): int
    {
        return max(2000, min(2100, $this->year ?: (int) now()->year));
    }

    protected function chosenMonth(): int
    {
        return max(1, min(12, $this->month ?: (int) now()->month));
    }

    /** @return array<int, string> */
    public function monthOptions(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all();
    }

    /** @return list<int> */
    public function yearOptions(): array
    {
        return range((int) now()->year - 4, (int) now()->year + 1);
    }
}
