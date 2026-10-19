<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Exports\ReportDocument;
use App\Domain\Reporting\KpiRegistry;
use App\Domain\Reporting\ReportCatalogue;
use App\Domain\Semen\Actions\GetSemenProduction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Builds one report of the Reports group as a ReportDocument, so the screen preview and the PDF / Excel / CSV downloads are the same
 * tables. Either "at a glance" (this week, this month and this year so far, side by side) or over one chosen period. Only indicators the
 * user may view are included (as everywhere, via GetKpis). Flows such as doses and feed produced are totals for the period; levels such
 * as stock and what is owed are as at its end.
 */
class BuildReport
{
    public const GLANCE = 'glance';

    public const CUSTOM = 'custom';

    private const DATE = 'd M Y';

    public function __construct(private readonly GetKpis $kpis, private readonly GetSemenProduction $semen) {}

    public function __invoke(string $key, string $mode, ?CarbonInterface $from, ?CarbonInterface $to, User $for): ReportDocument
    {
        $report = ReportCatalogue::all()[$key] ?? throw new InvalidArgumentException("Unknown report [{$key}].");
        $areas = ReportCatalogue::visibleAreas($report, $for);

        if ($areas === []) {
            throw new InvalidArgumentException('You may not run this report.');
        }

        return $mode === self::GLANCE ? $this->glance($key, $report['title'], $areas, $for) : $this->custom($key, $report['title'], $areas, $from, $to, $for);
    }

    /** @param list<string> $areas */
    private function glance(string $key, string $title, array $areas, User $for): ReportDocument
    {
        $today = CarbonImmutable::today();
        $periods = ['This week' => $today->startOfWeek(), 'This month' => $today->startOfMonth(), 'This year' => $today->startOfYear()];
        $values = array_map(fn (CarbonImmutable $start) => ($this->kpis)($start, $today, $for, $areas), $periods);

        $doc = new ReportDocument($title, 'to '.$today->format(self::DATE));
        $doc = $doc->with('Periods covered', ['Period', 'From', 'To'], array_map(fn (string $name, CarbonImmutable $start) => [$name, $start->format(self::DATE), $today->format(self::DATE)], array_keys($periods), $periods));

        foreach ($areas as $area) {
            $rows = [];
            foreach ($values['This week'] as $kpiKey => $kpi) {
                if ($kpi['area'] === $area) {
                    $rows[] = [$kpi['label'], ...array_map(fn (array $set) => KpiRegistry::format($kpi['unit'], $set[$kpiKey]['value']), array_values($values))];
                }
            }
            $doc = $doc->with(KpiRegistry::AREAS[$area][0], ['Indicator', ...array_keys($periods)], $rows);
        }

        return $key === 'semen' ? $this->boarSection($doc, $periods['This month'], $today, 'Doses per boar, this month') : $doc;
    }

    /** @param list<string> $areas */
    private function custom(string $key, string $title, array $areas, ?CarbonInterface $from, ?CarbonInterface $to, User $for): ReportDocument
    {
        if (! $from || ! $to) {
            throw new InvalidArgumentException('Choose the start and end dates.');
        }

        $values = ($this->kpis)($from, $to, $for, $areas);
        $doc = new ReportDocument($title, $from->format(self::DATE).' to '.$to->format(self::DATE));

        foreach ($areas as $area) {
            $rows = collect($values)->where('area', $area)->map(fn (array $kpi) => [$kpi['label'], KpiRegistry::format($kpi['unit'], $kpi['value'])])->values()->all();
            $doc = $doc->with(KpiRegistry::AREAS[$area][0], ['Indicator', 'Value'], $rows);
        }

        return $key === 'semen' ? $this->boarSection($doc, $from, $to, 'Doses per boar') : $doc;
    }

    private function boarSection(ReportDocument $doc, CarbonInterface $from, CarbonInterface $to, string $title): ReportDocument
    {
        $rows = ($this->semen)($from, $to)['rows']->map(fn (array $r) => [
            $r['boar'], $r['status'], $r['collections'], $r['passed'], $r['failed'], $r['doses'], $r['target_doses'], $r['attainment_percent'] !== null ? $r['attainment_percent'].'%' : '-',
        ])->all();

        return $doc->with($title, ['Boar', 'Status', 'Collections', 'Passed', 'Failed', 'Doses', 'Target', 'Against target'], $rows);
    }
}
