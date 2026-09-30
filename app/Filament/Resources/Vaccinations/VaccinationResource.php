<?php

namespace App\Filament\Resources\Vaccinations;

use App\Domain\Health\Models\Vaccination;
use App\Filament\Resources\Vaccinations\Pages\CreateVaccination;
use App\Filament\Resources\Vaccinations\Pages\ListVaccinations;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\HealthForms;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VaccinationResource extends Resource
{
    protected static ?string $model = Vaccination::class;

    protected static ?string $navigationLabel = 'Vaccinations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AnimalPicker::any()->required(),
            ...HealthForms::vaccination(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'medicine', 'schedule', 'batch']))
            ->columns([
                TextColumn::make('administered_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('medicine.name')->label('Vaccine'),
                TextColumn::make('schedule.name')->label('Schedule')->placeholder('Ad hoc'),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('vaccination_schedule_id')->label('Schedule')->relationship('schedule', 'name'),
            ])
            ->recordActions([

            ])
            ->defaultSort('administered_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaccinations::route('/'),
            'create' => CreateVaccination::route('/create'),
        ];
    }
}
