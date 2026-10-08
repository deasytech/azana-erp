<?php

namespace App\Filament\Resources\Treatments;

use App\Domain\Health\Models\Treatment;
use App\Filament\Resources\Treatments\Pages\CreateTreatment;
use App\Filament\Resources\Treatments\Pages\ListTreatments;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\FormSections;
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

class TreatmentResource extends Resource
{
    protected static ?string $model = Treatment::class;

    protected static ?string $navigationLabel = 'Treatments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Animal', 'Which animal this is about.', [AnimalPicker::any()->required()], Heroicon::OutlinedTag),
            FormSections::make('Treatment', 'The medicine, dose and withdrawal period.', HealthForms::treatment(), Heroicon::OutlinedHeart),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'medicine', 'batch']))
            ->columns([
                TextColumn::make('administered_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('medicine.name')->label('Medicine')->searchable(),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-'),
                TextColumn::make('dose')->placeholder('-')->formatStateUsing(fn ($state, $record) => trim(rtrim(rtrim((string) $state, '0'), '.').' '.$record->dose_unit)),
                TextColumn::make('withdrawal_days')->label('Withdrawal (days)'),
            ])
            ->filters([
                SelectFilter::make('medicine_id')->label('Medicine')->relationship('medicine', 'name'),
            ])
            ->recordActions([

            ])
            ->defaultSort('administered_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatments::route('/'),
            'create' => CreateTreatment::route('/create'),
        ];
    }
}
