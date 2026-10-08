<?php

namespace App\Filament\Resources\VaccinationSchedules;

use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Enums\LookupCategory;
use App\Filament\Resources\VaccinationSchedules\Pages\CreateVaccinationSchedule;
use App\Filament\Resources\VaccinationSchedules\Pages\EditVaccinationSchedule;
use App\Filament\Resources\VaccinationSchedules\Pages\ListVaccinationSchedules;
use App\Filament\Support\LookupSelect;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class VaccinationScheduleResource extends MasterResource
{
    protected static ?string $model = VaccinationSchedule::class;

    protected static ?string $codePrefix = 'VS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 40;

    protected static function fields(): array
    {
        return [
            static::nameField(),
            Select::make('medicine_id')->label('Vaccine')->required()->searchable()->options(fn () => Medicine::where('is_active', true)->whereHas('type', fn ($q) => $q->where('code', 'vaccine'))->orderBy('name')->pluck('name', 'id')),
            LookupSelect::make('category_id', 'category', LookupCategory::AnimalCategory, 'Applies to (leave empty for all)'),
            TextInput::make('first_dose_age_days')->label('First dose at age (days)')->numeric()->integer()->minValue(0)->required(),
            TextInput::make('repeat_interval_days')->label('Booster every (days)')->numeric()->integer()->minValue(1)->helperText('Leave empty for a single-dose schedule.'),
            Textarea::make('notes'),
        ];
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('name')->searchable(),
            TextColumn::make('medicine.name')->label('Vaccine'),
            TextColumn::make('category.name')->label('Applies to')->placeholder('All'),
            TextColumn::make('first_dose_age_days')->label('First dose (days)'),
            TextColumn::make('repeat_interval_days')->label('Booster (days)')->placeholder('Single dose'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaccinationSchedules::route('/'),
            'create' => CreateVaccinationSchedule::route('/create'),
            'edit' => EditVaccinationSchedule::route('/{record}/edit'),
        ];
    }
}
