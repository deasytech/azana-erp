<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Domain\Farm\Models\Farm;
use App\Domain\Production\Actions\GetBatchPerformance;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\ProductionBatches\Pages\Concerns\HasBatchActions;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewProductionBatch extends ViewRecord
{
    use HasBatchActions, NotifiesDomainErrors;

    protected static string $resource = ProductionBatchResource::class;

    /** Weigh-in dates that bound the growth / FCR period (empty = first and latest). */
    public ?string $periodFrom = null;

    public ?string $periodTo = null;

    /** @var array<string, int|string|null>|null */
    protected ?array $performanceMemo = null;

    /** Performance for the chosen period, computed once per request. */
    protected function performance(): array
    {
        if ($this->performanceMemo !== null) {
            return $this->performanceMemo;
        }

        try {
            $from = $this->periodFrom ? Carbon::parse($this->periodFrom) : null;
            $to = $this->periodTo ? Carbon::parse($this->periodTo) : null;

            return $this->performanceMemo = app(GetBatchPerformance::class)($this->record, $from, $to);
        } catch (DomainException|InvalidFormatException $e) {
            // The period properties are client-writable, so unparseable dates are handled like an invalid period.
            $this->periodFrom = $this->periodTo = null;
            $message = $e instanceof DomainException ? $e->getMessage() : 'The chosen period is not valid.';

            return $this->performanceMemo = ['period_error' => $message] + app(GetBatchPerformance::class)($this->record);
        }
    }

    /** Also called when a history tab changes the batch (e.g. a void), so the figures never go stale. */
    #[On('production-batch-changed')]
    public function refreshBatch(): void
    {
        $this->record->refresh();
        $this->performanceMemo = null;
    }

    public function infolist(Schema $schema): Schema
    {
        $perf = fn (string $key, string $label, ?callable $format = null) => TextEntry::make("perf_{$key}")->label($label)
            ->state(fn () => ($v = $this->performance()[$key] ?? null) === null ? '-' : ($format ? $format($v) : (string) $v));
        $money = fn ($minor) => Money::ofMinor((int) $minor, Farm::defaultCurrency())->format();

        return $schema->components([
            Section::make('Batch')->columns(4)->schema([
                TextEntry::make('code')->weight('bold')->copyable(),
                TextEntry::make('name'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('stage.name')->label('Stage'),
                TextEntry::make('pen.code')->label('Pen')->placeholder('-'),
                TextEntry::make('breed.name')->label('Breed')->placeholder('-'),
                TextEntry::make('started_on')->date(),
                TextEntry::make('source_note')->label('Source')->placeholder('-'),
            ]),
            Section::make('Performance')->columns(4)->schema([
                $perf('heads', 'Pigs now'), $perf('placed', 'Pigs placed'),
                $perf('mortality_percent', 'Mortality', fn ($v) => "{$v}% ({$this->performance()['mortality']} pigs)"),
                $perf('days_on_feed', 'Days on feed'),
                $perf('latest_weight_kg', 'Latest average weight', fn ($v) => "{$v} kg (".($this->performance()['latest_weigh_in'] ?? '-').')'),
                $perf('adg_kg', 'Average daily gain', fn ($v) => "{$v} kg/day"),
                $perf('fcr', 'Feed conversion (FCR)'),
                $perf('period_days', 'Measured over', fn ($v) => "{$v} days ({$this->performance()['period_start']} to {$this->performance()['period_end']})"),
                $perf('gain_kg', 'Weight gained', fn ($v) => "{$v} kg"),
                $perf('period_feed_kg', 'Feed in period', fn ($v) => "{$v} kg"),
                $perf('target_weight_kg', 'Market weight', fn ($v) => "{$v} kg"),
                $perf('expected_market_on', 'Expected market date', fn ($v) => Carbon::parse($v)->format('d M Y').' ('.$this->performance()['days_to_market'].' days)'),
            ]),
            Section::make('Costs')->columns(3)->schema([
                $perf('entry_cost_minor', 'Entry cost', $money), $perf('feed_cost_minor', 'Feed cost', $money), $perf('other_cost_minor', 'Other costs', $money),
                $perf('total_cost_minor', 'Total cost', $money), $perf('cost_per_pig_minor', 'Cost per pig', $money), $perf('cost_per_kg_gain_minor', 'Cost per kg gained', $money),
            ]),
            TextEntry::make('period_error')->hiddenLabel()->color('danger')->visible(fn () => isset($this->performance()['period_error']))
                ->state(fn () => $this->performance()['period_error'] ?? ''),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->batchActions(),
            Action::make('period')->label('Choose period')->icon('heroicon-o-calendar-days')->color('gray')
                ->schema([
                    Select::make('from')->label('From weigh-in')->options(fn () => $this->weighInDates())->placeholder('First'),
                    Select::make('to')->label('To weigh-in')->options(fn () => $this->weighInDates())->placeholder('Latest'),
                ])
                ->action(function (array $data) {
                    [$this->periodFrom, $this->periodTo] = [$data['from'] ?? null, $data['to'] ?? null];
                    $this->performanceMemo = null;
                }),
        ];
    }

    /** @return array<string, string> valid weigh-in dates, keyed by date */
    private function weighInDates(): array
    {
        return $this->record->weighIns()->whereNull('voided_at')->orderBy('weighed_on')->get()
            ->mapWithKeys(fn ($w) => [$w->weighed_on->toDateString() => $w->weighed_on->format('d M Y')." - {$w->average_weight_kg} kg"])->all();
    }
}
