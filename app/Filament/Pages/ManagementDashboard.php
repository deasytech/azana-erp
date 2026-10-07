<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\Actions\GetTargetVsActual;
use App\Domain\Reporting\KpiRegistry;
use App\Filament\Concerns\SelectsReportMonth;
use App\Filament\Widgets\TargetAttainmentChartWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/** The management cockpit: the executive summary and a dashboard for each part of the farm, each indicator against its target. */
class ManagementDashboard extends Page
{
    use SelectsReportMonth;

    protected string $view = 'filament.pages.management-dashboard';

    protected static ?string $navigationLabel = 'Dashboards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Management dashboards';

    #[Url]
    public string $area = 'executive';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->mountSelectsReportMonth();
    }

    /** @return array<string, string> the dashboards this user may open: label by key */
    public function areas(): array
    {
        $user = auth()->user();

        return collect(KpiRegistry::AREAS)->filter(fn ($a, $key) => $key === 'executive' || $user->can("{$a[1]}.view"))->map(fn ($a) => $a[0])->all();
    }

    public function chosenArea(): string
    {
        return array_key_exists($this->area, $this->areas()) ? $this->area : 'executive';
    }

    /** @return array<string, array<string, mixed>> */
    public function getKpisProperty(): array
    {
        $area = $this->chosenArea();

        return array_filter(app(GetTargetVsActual::class)($this->chosenYear(), $this->chosenMonth(), auth()->user(), now()), fn ($k) => $area === 'executive' ? $k['executive'] : $k['area'] === $area);
    }

    public function periodLabel(): string
    {
        return date('F', mktime(0, 0, 0, $this->chosenMonth(), 1)).' '.$this->chosenYear();
    }

    /** The chosen dashboard drawn against target, below the indicator cards. */
    protected function getFooterWidgets(): array
    {
        return [TargetAttainmentChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return ['year' => $this->chosenYear(), 'month' => $this->chosenMonth(), 'area' => $this->chosenArea()];
    }

    /** Tell the chart when the month, year or dashboard changes while it is open. */
    public function updated(string $property): void
    {
        if (in_array($property, ['year', 'month', 'area'], true)) {
            $this->dispatch('report-filter-changed', year: $this->chosenYear(), month: $this->chosenMonth(), area: $this->chosenArea());
        }
    }
}
