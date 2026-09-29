<?php

namespace App\Filament\Resources\Animals\Pages;

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\GetAnimalHistory;
use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Pen;
use App\Enums\AnimalStatus;
use App\Enums\LookupCategory;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Animals\AnimalResource;
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

class ViewAnimal extends ViewRecord
{
    use NotifiesDomainErrors;

    protected static string $resource = AnimalResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Passport')->columns(3)->schema([
                TextEntry::make('animal_number')->label('Permanent number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('sex')->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('breed.name')->label('Breed')->placeholder('-'),
                TextEntry::make('geneticLine.name')->label('Genetic line')->placeholder('-'),
                TextEntry::make('birth_date')->date()->placeholder('Unknown')->suffix(fn (Animal $r) => $r->birth_date_estimated ? ' (estimated)' : ''),
                TextEntry::make('source')->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('source_name')->label('Source')->placeholder('-'),
                TextEntry::make('qr')->label('QR / barcode payload')->state(fn (Animal $r) => $r->qrPayload())->copyable()->columnSpanFull(),
            ]),
            Section::make('Current state')->columns(3)->schema([
                TextEntry::make('position')->state(fn (Animal $r) => $r->positionLabel()),
                TextEntry::make('weight')->label('Latest weight')->state(fn (Animal $r) => ($w = $r->latestWeight()) ? "{$w->weight_kg} kg on {$w->weighed_at->format('d M Y')}" : 'Not weighed'),
                TextEntry::make('parents')->label('Parents')->state(fn (Animal $r) => collect([
                    'Sire' => $r->parentage?->sire?->animal_number ?? $r->parentage?->sire_note,
                    'Dam' => $r->parentage?->dam?->animal_number ?? $r->parentage?->dam_note,
                ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' | ') ?: 'Not recorded'),
            ]),
            Section::make('Lifecycle history')->collapsible()->schema([
                ViewEntry::make('history')->hiddenLabel()->view('filament.animals.history')
                    ->state(fn (Animal $r) => app(GetAnimalHistory::class)($r)),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->moveAction(),
            $this->weightAction(),
            $this->statusAction(),
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
                Select::make('status')->required()->options(collect(AnimalStatus::cases())->filter->isTerminal()->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()),
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

    private function afterAction(string $message): void
    {
        $this->record->refresh();
        Notification::make()->title($message)->success()->send();
    }
}
