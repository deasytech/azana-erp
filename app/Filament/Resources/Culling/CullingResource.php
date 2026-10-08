<?php

namespace App\Filament\Resources\Culling;

use App\Domain\Health\Models\CullingRecord;
use App\Filament\Resources\Culling\Pages\CreateCulling;
use App\Filament\Resources\Culling\Pages\ListCullings;
use App\Filament\Support\AnimalPicker;
use App\Filament\Support\FormSections;
use App\Filament\Support\HealthForms;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CullingResource extends Resource
{
    protected static ?string $model = CullingRecord::class;

    protected static ?string $navigationLabel = 'Culling';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?int $navigationSort = 18;

    public static function canCreate(): bool
    {
        return auth()->user()?->can('approve', CullingRecord::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSections::make('Animal', 'Which animal this is about.', [AnimalPicker::any()->required()], Heroicon::OutlinedTag),
            FormSections::make('Culling record', 'Why the animal is being removed from the herd.', HealthForms::culling(), Heroicon::OutlinedHeart),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['animal', 'reason']))
            ->columns([
                TextColumn::make('culled_on')->date()->sortable(),
                TextColumn::make('animal.animal_number')->label('Animal')->searchable(),
                TextColumn::make('reason.name')->label('Reason'),
                TextColumn::make('weight_kg')->label('Weight (kg)')->numeric(decimalPlaces: 2),
                TextColumn::make('health_status')->formatStateUsing(fn ($state) => $state->label())->badge(),
                TextColumn::make('disposal')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('disposal_value_minor')->label('Value')->state(fn (CullingRecord $r) => Money::ofMinor($r->disposal_value_minor ?? 0, 'NGN')->format()),
            ])
            ->filters([
                SelectFilter::make('reason_id')->label('Reason')->relationship('reason', 'name'),
            ])
            ->recordActions([

            ])
            ->defaultSort('culled_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCullings::route('/'),
            'create' => CreateCulling::route('/create'),
        ];
    }
}
