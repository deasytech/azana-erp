<?php

namespace App\Filament\Resources\Mortality;

use App\Domain\Health\Models\MortalityRecord;
use App\Filament\Resources\Mortality\Pages\CreateMortality;
use App\Filament\Resources\Mortality\Pages\ListMortalities;
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

class MortalityResource extends Resource
{
    protected static ?string $model = MortalityRecord::class;

    protected static ?string $navigationLabel = 'Mortality';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedXCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AnimalPicker::any()->required(),
            ...HealthForms::mortality(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'category', 'cause', 'pen', 'litter', 'breed']))
            ->columns([
                TextColumn::make('died_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('category.name')->label('Stage')->badge(),
                TextColumn::make('cause.name')->label('Cause'),
                TextColumn::make('age_days')->label('Age (days)')->placeholder('-'),
                TextColumn::make('pen.code')->label('Pen')->placeholder('-'),
                TextColumn::make('litter.litter_number')->label('Litter')->placeholder('-')->toggleable(),
                TextColumn::make('breed.name')->label('Breed')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('cause_id')->label('Cause')->relationship('cause', 'name'),
                SelectFilter::make('category_id')->label('Stage')->relationship('category', 'name'),
            ])
            ->recordActions([

            ])
            ->defaultSort('died_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMortalities::route('/'),
            'create' => CreateMortality::route('/create'),
        ];
    }
}
