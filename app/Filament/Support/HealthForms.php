<?php

namespace App\Filament\Support;

use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\Pen;
use App\Domain\Health\Models\Disease;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Enums\CullHealthStatus;
use App\Enums\DisposalType;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Enums\LookupCategory;
use App\Enums\QuarantineType;
use App\Filament\Resources\Animals\AnimalResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/** Form fields for the health screens, shared by the animal profile buttons and the standalone screens. */
class HealthForms
{
    private const ROUTES = ['oral' => 'Oral', 'injection_im' => 'Injection (IM)', 'injection_sc' => 'Injection (SC)', 'topical' => 'Topical', 'in_feed' => 'In feed', 'in_water' => 'In water', 'other' => 'Other'];

    /** @return list<Component> */
    public static function treatment(): array
    {
        return [
            Select::make('medicine_id')->label('Medicine')->required()->searchable()->live()
                ->options(fn () => Medicine::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            self::batch(),
            DatePicker::make('administered_on')->default(now())->maxDate(now())->required(),
            TextInput::make('dose')->numeric()->minValue(0)->step(0.001),
            TextInput::make('dose_unit')->maxLength(20)->placeholder('ml, mg, g...'),
            Select::make('route')->options(self::ROUTES),
            TextInput::make('withdrawal_days')->numeric()->integer()->minValue(0)->helperText('Leave empty to use the medicine\'s withdrawal period; you may only lengthen it.'),
            Textarea::make('notes'),
        ];
    }

    /** @return list<Component> */
    public static function vaccination(): array
    {
        return [
            Select::make('schedule_id')->label('Vaccination schedule')->searchable()->live()
                ->afterStateUpdated(function ($set) {
                    // The schedule decides the vaccine; drop any earlier vaccine or batch choice.
                    $set('medicine_id', null);
                    $set('batch_id', null);
                })
                ->options(fn () => VaccinationSchedule::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->helperText('Choose a schedule, or pick the vaccine below for an ad-hoc vaccination.'),
            Select::make('medicine_id')->label('Vaccine')->searchable()->live()
                ->options(fn () => Medicine::where('is_active', true)->whereHas('type', fn ($q) => $q->where('code', 'vaccine'))->orderBy('name')->pluck('name', 'id'))
                ->hidden(fn ($get) => filled($get('schedule_id'))),
            self::batch(fn ($get) => filled($get('schedule_id')) ? VaccinationSchedule::find($get('schedule_id'))?->medicine_id : $get('medicine_id')),
            DatePicker::make('administered_on')->default(now())->maxDate(now())->required(),
            TextInput::make('dose')->numeric()->minValue(0)->step(0.001),
            Textarea::make('notes'),
        ];
    }

    /** @return list<Component> */
    public static function caseReport(): array
    {
        return [
            Select::make('kind')->options(AnimalResource::enumOptions(HealthEventKind::cases()))->required()->default(HealthEventKind::Illness->value),
            Select::make('severity')->options(AnimalResource::enumOptions(HealthSeverity::cases()))->required()->default(HealthSeverity::Mild->value),
            DatePicker::make('observed_on')->default(now())->maxDate(now())->required(),
            Select::make('disease_id')->label('Suspected disease')->searchable()->options(fn () => Disease::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            Textarea::make('symptoms'),
        ];
    }

    /** @return list<Component> */
    public static function quarantine(): array
    {
        return [
            Select::make('type')->options(AnimalResource::enumOptions(QuarantineType::cases()))->required()->default(QuarantineType::Isolation->value),
            DatePicker::make('started_on')->default(now())->maxDate(now())->required(),
            Textarea::make('reason')->required(),
            Select::make('pen_id')->label('Move to pen (optional)')->searchable()->options(fn () => Pen::where('is_active', true)->orderBy('code')->pluck('code', 'id')),
            Select::make('location_id')->label('Or move to location (optional)')->searchable()->options(fn () => Location::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
        ];
    }

    /** @return list<Component> */
    public static function mortality(): array
    {
        return [
            DatePicker::make('died_on')->default(now())->maxDate(now())->required(),
            LookupSelect::options('cause_id', LookupCategory::MortalityCause, 'Cause of death')->required(),
            Select::make('disease_id')->label('Disease (if known)')->searchable()->options(fn () => Disease::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            Textarea::make('notes'),
        ];
    }

    /** @return list<Component> */
    public static function culling(): array
    {
        return [
            DatePicker::make('culled_on')->default(now())->maxDate(now())->required(),
            LookupSelect::options('reason_id', LookupCategory::CullReason, 'Reason')->required(),
            TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(0.01)->step(0.01)->required(),
            Select::make('health_status')->options(AnimalResource::enumOptions(CullHealthStatus::cases()))->required(),
            Select::make('disposal')->options(AnimalResource::enumOptions(DisposalType::cases()))->required(),
            MoneyInput::make('disposal_value_minor', 'Sale / disposal value')->required()->default('0.00')->helperText('Use 0 when there is no value.'),
            Textarea::make('notes'),
        ];
    }

    /** Batches of the chosen medicine that are in use and not expired. */
    private static function batch(?\Closure $medicineOf = null): Select
    {
        $medicineOf ??= fn ($get) => $get('medicine_id');

        return Select::make('batch_id')->label('Batch (optional)')->searchable()
            ->options(fn ($get) => ($id = $medicineOf($get))
                ? MedicineBatch::where('medicine_id', $id)->where('is_active', true)->whereDate('expiry_date', '>=', now()->toDateString())->orderBy('expiry_date')->pluck('batch_number', 'id')->all()
                : []);
    }
}
