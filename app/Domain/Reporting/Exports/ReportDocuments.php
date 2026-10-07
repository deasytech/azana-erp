<?php

namespace App\Domain\Reporting\Exports;

use App\Domain\Farm\Models\Farm;
use App\Domain\Finance\Actions\GetReceivablesPayables;
use App\Domain\Reporting\KpiRegistry;
use App\Support\Money;
use Carbon\CarbonInterface;

/** Builds ReportDocuments (and so CSV / Excel / PDF files) from the reports' own figures, in the same words the screens use. */
class ReportDocuments
{
    private const DATE = 'd M Y';

    private function money(?int $minor): string
    {
        return $minor === null ? '-' : Money::ofMinor($minor, Farm::defaultCurrency())->format();
    }

    private function pct(?string $value): string
    {
        return $value === null ? '-' : $value.'%';
    }

    /** @param array<string, array<string, mixed>> $kpis from GetTargetVsActual */
    public function targetVsActual(array $kpis, string $label): ReportDocument
    {
        return $this->kpiSections(new ReportDocument('Target vs actual', $label), $kpis);
    }

    /** @param array<string, mixed> $r from GetMonthlyReport */
    public function monthly(array $r): ReportDocument
    {
        $doc = new ReportDocument('Monthly management report', $r['label']);
        $doc = $doc->with('About this report', ['Generated', 'By'], [[$r['generated_at']->format(self::DATE.' H:i'), $r['generated_by']]]);
        $doc = $this->kpiSections($doc, $r['kpis']);

        if ($p = $r['profitability']) {
            $doc = $this->profitabilitySection($doc, $p);
        }

        if ($c = $r['cash_flow']) {
            $doc = $this->cashFlowSections($doc, $c);
        }

        if ($a = $r['ageing']) {
            $doc = $this->ageingSections($doc, $a);
        }

        if ($b = $r['budget']) {
            $doc = $doc->with("Budget: {$b['name']} (January to ".date('F', mktime(0, 0, 0, $b['through_month'], 1)).')', ['Account', 'Cost centre', 'Budget', 'Actual', 'Variance'],
                array_map(fn ($row) => [$row['account']->label(), $row['cost_centre']->name ?? '-', $this->money($row['budget_minor']), $this->money($row['actual_minor']), $this->money($row['variance_minor']).($row['favourable'] ? ' (favourable)' : ' (adverse)')], $b['rows']));
        }

        if ($t = $r['tasks']) {
            $doc = $doc->with('Tasks', ['Created this month', 'Completed this month', 'Overdue'], [[$t['created'], $t['completed'], $t['overdue']]]);
        }

        return $doc->with('Alerts standing now', ['Critical', 'Warnings', 'Information'], [[$r['alerts']['danger'], $r['alerts']['warning'], $r['alerts']['info']]]);
    }

    /** @param array<string, mixed> $p from GetProfitability */
    public function profitability(array $p, CarbonInterface $from, CarbonInterface $to): ReportDocument
    {
        return $this->profitabilitySection(new ReportDocument('Profitability by business unit', $from->format(self::DATE).' to '.$to->format(self::DATE)), $p);
    }

    /** @param array<string, mixed> $c from GetCashFlow */
    public function cashFlow(array $c, CarbonInterface $from, CarbonInterface $to): ReportDocument
    {
        return $this->cashFlowSections(new ReportDocument('Cash flow', $from->format(self::DATE).' to '.$to->format(self::DATE)), $c);
    }

    /** @param array<string, mixed> $a from GetReceivablesPayables */
    public function ageing(array $a, CarbonInterface $asOf): ReportDocument
    {
        return $this->ageingSections(new ReportDocument('Receivables and payables', 'As at '.$asOf->format(self::DATE)), $a);
    }

    /** @param array<string, mixed> $t from GetTrialBalance */
    public function trialBalance(array $t): ReportDocument
    {
        return (new ReportDocument('Trial balance', 'As at '.now()->format(self::DATE)))->with('Accounts', ['Account', 'Type', 'Debits', 'Credits', 'Balance'],
            array_map(fn ($r) => [$r['account']->label(), $r['account']->type->label(), $this->money($r['debit_minor']), $this->money($r['credit_minor']), $this->money($r['balance_minor'])], $t['rows']))
            ->with('Totals', ['Debits', 'Credits', 'Balanced'], [[$this->money($t['debit_minor']), $this->money($t['credit_minor']), $t['balanced'] ? 'Yes' : 'NO']]);
    }

    /** @param array<string, array<string, mixed>> $kpis */
    private function kpiSections(ReportDocument $doc, array $kpis): ReportDocument
    {
        foreach (collect($kpis)->groupBy('area') as $area => $rows) {
            $doc = $doc->with(KpiRegistry::AREAS[$area][0], ['Indicator', 'Actual', 'Target', 'Reached', 'Status'], $rows->map(fn ($k) => [
                $k['label'], KpiRegistry::format($k['unit'], $k['value']), $k['target'] !== null ? KpiRegistry::format($k['unit'], $k['target']) : '-',
                $k['attainment_percent'] !== null ? $k['attainment_percent'].'%' : '-', ['met' => 'Met', 'missed' => 'Missed', 'none' => '-'][$k['status']],
            ])->all());
        }

        return $doc;
    }

    /** @param array<string, mixed> $p */
    private function profitabilitySection(ReportDocument $doc, array $p): ReportDocument
    {
        $t = $p['totals'];

        return $doc->with('Business units', ['Business unit', 'Revenue', 'Direct cost', 'Gross margin', 'Overheads', 'Net margin'],
            [...array_map(fn ($r) => [$r['centre']->name, $this->money($r['revenue_minor']), $this->money($r['direct_cost_minor']), $this->money($r['gross_margin_minor']), $this->money($r['overhead_minor']), $this->money($r['net_margin_minor'])], $p['rows']),
                ['Whole farm', $this->money($t['revenue_minor']), $this->money($t['direct_cost_minor']), $this->money($t['gross_margin_minor']).' ('.$this->pct($t['gross_margin_percent']).')', $this->money($t['overhead_minor']), $this->money($t['net_margin_minor']).' ('.$this->pct($t['net_margin_percent']).')']]);
    }

    /** @param array<string, mixed> $c */
    private function cashFlowSections(ReportDocument $doc, array $c): ReportDocument
    {
        $lines = fn (array $items) => array_map(fn ($amount, $purpose) => [$purpose, $this->money($amount)], $items, array_keys($items));

        return $doc->with('Summary', ['Opening balance', 'Money in', 'Money out', 'Closing balance'], [[$this->money($c['opening_minor']), $this->money($c['in_minor']), $this->money($c['out_minor']), $this->money($c['closing_minor'])]])
            ->with('Money in', ['For', 'Amount'], $lines($c['inflows']))->with('Money out', ['For', 'Amount'], $lines($c['outflows']));
    }

    /** @param array<string, mixed> $a */
    private function ageingSections(ReportDocument $doc, array $a): ReportDocument
    {
        foreach (['receivables' => 'Owed by customers', 'payables' => 'Owed to suppliers'] as $key => $title) {
            $doc = $doc->with($title.': '.$this->money($a[$key]['total_minor']), ['Name', ...array_values(GetReceivablesPayables::BUCKETS), 'Total'],
                array_map(fn ($p) => [$p['name'], ...array_map(fn ($b) => $this->money($p[$b]), array_keys(GetReceivablesPayables::BUCKETS)), $this->money($p['total_minor'])], $a[$key]['parties']));
        }

        return $doc;
    }
}
