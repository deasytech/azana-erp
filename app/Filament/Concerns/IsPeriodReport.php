<?php

namespace App\Filament\Concerns;

/** A report page over a period (default: this month so far), run by one domain action called with the start and end dates. */
trait IsPeriodReport
{
    use ValidatesReportPeriod;

    /** @return class-string the action, invoked with the period's start and end */
    abstract protected function reportAction(): string;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return list<string> problems with the chosen period (the properties are client-writable, so they are checked) */
    public function inputErrors(): array
    {
        return $this->periodErrors();
    }

    /** @return ?array<string, mixed> null while the period is invalid */
    public function getReportProperty(): ?array
    {
        return $this->inputErrors() === [] ? app($this->reportAction())($this->periodStart(), $this->periodEnd()) : null;
    }
}
