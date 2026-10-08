<?php

namespace App\Filament\Resources\Animals\Pages;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\GetAnimalHistory;
use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Pen;
use App\Domain\Litter\Actions\GetSowPerformance;
use App\Domain\Production\Actions\GetAnimalGrowth;
use App\Domain\Production\Models\ProductionBatchAnimal;
use App\Enums\AnimalStatus;
use App\Enums\LookupCategory;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Pages\TraceExplorer;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Animals\Pages\Concerns\HasHealthActions;
use App\Filament\Support\LookupSelect;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewAnimal extends ViewRecord
{
    use HasHealthActions, NotifiesDomainErrors;

    protected static string $resource = AnimalResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Passport')->description('Who the animal is.')->icon(Heroicon::OutlinedIdentification)->columns(3)->schema([
                TextEntry::make('animal_number')->label('Permanent number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('sex')->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('breed.name')->label('Breed')->placeholder('-'),
                TextEntry::make('geneticLine.name')->label('Genetic line')->placeholder('-'),
                TextEntry::make('birth_date')->date()->placeholder('Unknown')->suffix(fn (Animal $r) => $r->birth_date_estimated ? ' (estimated)' : ''),
                TextEntry::make('source')->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('source_name')->label('Source')->placeholder('-'),
                TextEntry::make('qr')->label('QR / barcode payload')->state(fn (Animal $r) => $r->qrPayload())->copyable()->columnSpanFull(),
            ]),
            Section::make('Current state')->description('Where it is and how big it is.')->icon(Heroicon::OutlinedMapPin)->columns(3)->schema([
                TextEntry::make('position')->state(fn (Animal $r) => $r->positionLabel()),
                TextEntry::make('weight')->label('Latest weight')->state(fn (Animal $r) => ($w = $r->latestWeight()) ? "{$w->weight_kg} kg on {$w->weighed_at->format('d M Y')}" : 'Not weighed'),
                TextEntry::make('parents')->label('Parents')->state(fn (Animal $r) => collect([
                    'Sire' => $r->parentage?->sire?->animal_number ?? $r->parentage?->sire_note,
                    'Dam' => $r->parentage?->dam?->animal_number ?? $r->parentage?->dam_note,
                ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' | ') ?: 'Not recorded'),
            ]),
            Section::make('Health')->description('Restrictions and recent care.')->icon(Heroicon::OutlinedHeart)->columns(3)->schema([
                TextEntry::make('withdrawal')->label('Withdrawal')
                    ->state(fn (Animal $r) => ($w = $this->restrictions($r)['withdrawals']->first()) ? "Until {$w->ends_on->format('d M Y')} ({$w->medicine->name})" : 'None')
                    ->color(fn (Animal $r) => $this->restrictions($r)['withdrawals']->isNotEmpty() ? 'danger' : null),
                TextEntry::make('quarantine')->label('Quarantine / isolation')
                    ->state(fn (Animal $r) => ($q = $this->restrictions($r)['quarantine']) ? ucfirst($q->type->value)." since {$q->started_on->format('d M Y')}" : 'None')
                    ->color(fn (Animal $r) => $this->restrictions($r)['quarantine'] ? 'warning' : null),
                TextEntry::make('open_cases')->label('Open health cases')->state(fn (Animal $r) => (string) $r->healthEvents()->where('status', 'open')->count()),
                TextEntry::make('last_treatment')->label('Last treatment')->state(fn (Animal $r) => ($t = $r->treatments()->with('medicine')->first()) ? "{$t->medicine->name}, {$t->administered_on->format('d M Y')}" : '-'),
                TextEntry::make('last_vaccination')->label('Last vaccination')->state(fn (Animal $r) => ($v = $r->vaccinations()->with('medicine')->first()) ? "{$v->medicine->name}, {$v->administered_on->format('d M Y')}" : '-'),
            ]),
            Section::make('Growth')->description('Gain and feed efficiency while growing.')->icon(Heroicon::OutlinedChartBar)->columns(4)->schema([
                TextEntry::make('batch')->label('Batch')->state(fn (Animal $r) => ProductionBatchAnimal::with('batch')->where('animal_id', $r->id)->whereNull('left_on')->first()?->batch->code ?? '-'),
                TextEntry::make('growth_adg')->label('Average daily gain')->state(fn (Animal $r) => ($v = $this->growth($r)['adg_kg']) === null ? '-' : "{$v} kg/day"),
                TextEntry::make('growth_gain')->label('Gained')->state(fn (Animal $r) => ($v = $this->growth($r)['gain_kg']) === null ? '-' : "{$v} kg over {$this->growth($r)['days']} days"),
                TextEntry::make('growth_fcr')->label('FCR (feed recorded)')->state(fn (Animal $r) => $this->growth($r)['fcr'] ?? '-'),
            ]),
            Section::make('Reproduction')->description('Litter performance of this female.')->icon(Heroicon::OutlinedSparkles)->columns(4)->visible(fn (Animal $r) => $r->isBreedingFemale())->schema(
                collect([
                    'status' => 'Reproductive status', 'parity' => 'Litters (parity)', 'avg_total_born' => 'Avg total born',
                    'avg_born_alive' => 'Avg born alive', 'avg_weaned' => 'Avg weaned', 'total_weaned' => 'Total weaned',
                    'pre_weaning_mortality_percent' => 'Pre-weaning mortality %', 'weaning_percent' => 'Weaning %',
                    'avg_birth_weight_kg' => 'Avg birth weight (kg)', 'avg_farrowing_interval_days' => 'Avg farrowing interval (days)',
                ])->map(fn ($label, $key) => TextEntry::make("perf_{$key}")->label($label)->formatStateUsing(fn ($state) => $state ?? '-')
                    ->state(fn (Animal $r) => ($v = app(GetSowPerformance::class)($r)[$key]) === null ? null : ucfirst(str_ends_with($key, 'percent') ? $v.'%' : (string) $v)))->values()->all(),
            ),
            Section::make('Lifecycle history')->description('Everything recorded about this animal, newest first.')->icon(Heroicon::OutlinedClock)->collapsible()->schema([
                ViewEntry::make('history')->hiddenLabel()->view('filament.animals.history')
                    ->state(fn (Animal $r) => app(GetAnimalHistory::class)($r)),
            ]),
        ]);
    }

    public function getSubheading(): ?string
    {
        return $this->record->category->name.' - '.$this->record->positionLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('trace')->label('Trace this animal')->icon('heroicon-o-magnifying-glass')->color('gray')
                ->visible(fn () => TraceExplorer::canAccess())
                ->url(fn () => TraceExplorer::urlFor('animal', $this->record->animal_number)),
            $this->moveAction(),
            $this->weightAction(),
            $this->statusAction(),
            ...$this->healthActions(),
            EditAction::make(),
        ];
    }

    private function moveAction(): Action
    {
        return Action::make('move')->label('Move')->icon('heroicon-o-arrow-right-circle')
            ->visible(fn () => $this->record->isActive() && auth()->user()->can('create', Animal::class))
            ->schema([
                Select::make('destination')->required()->searchable()->options(fn () => [
                    'Pens' => Pen::where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($p) => ["pen:{$p->id}" => $p->code])->all(),
                    'Locations' => Location::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($l) => ["loc:{$l->id}" => $l->name])->all(),
                ]),
                LookupSelect::options('reason_id', LookupCategory::MovementReason, 'Reason'),
                DateTimePicker::make('moved_at')->default(now())->required()->seconds(false),
                Textarea::make('notes'),
            ])
            ->action(function (array $data, Action $action) {
                [$kind, $id] = explode(':', $data['destination']);

                $this->attempt(fn () => app(RecordAnimalMovement::class)(
                    $this->record, $kind === 'pen' ? (int) $id : null, $kind === 'loc' ? (int) $id : null,
                    Carbon::parse($data['moved_at']), $data['reason_id'] ?? null, $data['notes'] ?? null,
                ), $action);

                $this->afterAction('Animal moved');
            });
    }

    private function weightAction(): Action
    {
        return Action::make('weigh')->label('Record weight')->icon('heroicon-o-scale')
            ->visible(fn () => $this->record->isActive() && auth()->user()->can('create', Animal::class))
            ->schema([
                TextInput::make('weight_kg')->label('Weight (kg)')->required()->numeric()->minValue(0.01)->step(0.01),
                DateTimePicker::make('weighed_at')->default(now())->required()->seconds(false),
                Textarea::make('notes'),
            ])
            ->action(function (array $data, Action $action) {
                $this->attempt(fn () => app(RecordWeight::class)(
                    $this->record, (string) $data['weight_kg'], Carbon::parse($data['weighed_at']), 'scale', $data['notes'] ?? null,
                ), $action);

                $this->afterAction('Weight recorded');
            });
    }

    private function statusAction(): Action
    {
        return Action::make('status')->label('Change status')->icon('heroicon-o-arrow-right-start-on-rectangle')->color('danger')
            ->visible(fn () => $this->record->isActive() && auth()->user()->can('approve', Animal::class))
            ->requiresConfirmation()
            ->modalDescription('This takes the animal off the farm. The status is final.')
            ->schema([
                Select::make('status')->required()->options(collect(AnimalStatus::cases())->filter(fn ($s) => $s->isTerminal() && ! in_array($s, [AnimalStatus::Dead, AnimalStatus::Culled], true))->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()),
                Textarea::make('reason')->required(),
                DateTimePicker::make('changed_at')->default(now())->required()->seconds(false),
            ])
            ->action(function (array $data, Action $action) {
                $this->attempt(fn () => app(ChangeAnimalStatus::class)(
                    $this->record, AnimalStatus::from($data['status']), $data['reason'], Carbon::parse($data['changed_at']),
                ), $action);

                $this->afterAction('Status changed');
            });
    }

    /** @var array<int|string, array<string, int|string|null>> */
    protected array $growthByAnimal = [];

    /** Growth figures for the record, computed once per request. */
    protected function growth(Animal $animal): array
    {
        return $this->growthByAnimal[$animal->getKey()] ??= app(GetAnimalGrowth::class)($animal);
    }

    private function afterAction(string $message): void
    {
        $this->record->refresh();
        $this->forgetRestrictions();
        $this->growthByAnimal = [];
        Notification::make()->title($message)->success()->send();
    }
}
