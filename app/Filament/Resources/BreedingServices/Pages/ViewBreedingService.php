<?php

namespace App\Filament\Resources\BreedingServices\Pages;

use App\Domain\Breeding\Actions\RecordAbortion;
use App\Domain\Breeding\Actions\RecordPregnancyCheck;
use App\Domain\Breeding\Models\BreedingService;
use App\Enums\PregnancyCheckMethod;
use App\Enums\PregnancyCheckResult;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\BreedingServices\BreedingServiceResource;
use App\Filament\Resources\Litters\FarrowingForm;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewBreedingService extends ViewRecord
{
    use NotifiesDomainErrors;

    protected static string $resource = BreedingServiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service')->columns(3)->schema([
                TextEntry::make('sow.animal_number')->label('Sow')->weight('bold'),
                TextEntry::make('serviced_on')->date(),
                TextEntry::make('method')->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('boar')->label('Boar / semen')->state(fn (BreedingService $r) => $r->semenBatch?->number ?? $r->boar?->animal_number ?? $r->semen_source ?? '-'),
                TextEntry::make('technician')->state(fn (BreedingService $r) => $r->technicianLabel() ?? '-'),
                TextEntry::make('outcome')->badge()->formatStateUsing(fn ($state) => $state->label()),
            ]),
            Section::make('Expected dates')->columns(5)->schema([
                TextEntry::make('expected_pregnancy_check_on')->label('Pregnancy check')->date(),
                TextEntry::make('expected_next_heat_on')->label('Return to heat')->date(),
                TextEntry::make('expected_farrowing_on')->label('Farrowing')->date(),
                TextEntry::make('expected_weaning_on')->label('Weaning')->date(),
                TextEntry::make('expected_next_service_on')->label('Next service')->date(),
            ]),
            Section::make('Pregnancy checks')->schema([
                TextEntry::make('checks')->hiddenLabel()->placeholder('No checks recorded yet.')
                    ->state(fn (BreedingService $r) => $r->checks->map(fn ($c) => "{$c->checked_on->format('d M Y')}: {$c->result->label()} ({$c->method->label()})")->all())
                    ->bulleted(),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $open = fn () => $this->record->outcome->isOpen() && auth()->user()->can('create', BreedingService::class);

        return [
            Action::make('check')->label('Pregnancy check')->icon('heroicon-o-magnifying-glass')->visible($open)
                ->schema([
                    Select::make('result')->options(AnimalResource::enumOptions(PregnancyCheckResult::cases()))->required(),
                    Select::make('method')->options(AnimalResource::enumOptions(PregnancyCheckMethod::cases()))->default(PregnancyCheckMethod::Ultrasound->value)->required(),
                    DatePicker::make('checked_on')->default(now())->maxDate(now())->required(),
                    Textarea::make('notes'),
                ])
                ->action(fn (array $data, Action $action) => $this->run(fn () => app(RecordPregnancyCheck::class)(
                    $this->record, PregnancyCheckResult::from($data['result']), Carbon::parse($data['checked_on']),
                    PregnancyCheckMethod::from($data['method']), $data['notes'] ?? null,
                ), $action, 'Pregnancy check recorded')),
            Action::make('farrow')->label('Record farrowing')->icon('heroicon-o-sparkles')->visible($open)
                ->schema(FarrowingForm::fields())
                ->action(function (array $data, Action $action) {
                    $litter = $this->attempt(fn () => FarrowingForm::submit($this->record->sow, $data, $this->record->id), $action);
                    $this->record->refresh();
                    Notification::make()->title("Litter {$litter->litter_number} created")->success()->send();
                }),
            Action::make('abort')->label('Record abortion')->icon('heroicon-o-x-circle')->color('danger')->visible($open)->requiresConfirmation()
                ->schema([DatePicker::make('occurred_on')->default(now())->maxDate(now())->required(), Textarea::make('reason')->required()])
                ->action(fn (array $data, Action $action) => $this->run(fn () => app(RecordAbortion::class)(
                    $this->record, Carbon::parse($data['occurred_on']), $data['reason'],
                ), $action, 'Abortion recorded')),
        ];
    }

    private function run(\Closure $callback, Action $action, string $message): void
    {
        $this->attempt($callback, $action);
        $this->record->refresh();
        Notification::make()->title($message)->success()->send();
    }
}
