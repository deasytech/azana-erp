<?php

namespace App\Filament\Resources\Litters;

use App\Domain\Litter\Models\Litter;
use App\Enums\LitterStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Litters\Pages\ListLitters;
use App\Filament\Resources\Litters\Pages\ViewLitter;
use App\Filament\Resources\Litters\RelationManagers\LossesRelationManager;
use App\Filament\Resources\Litters\RelationManagers\PigletsRelationManager;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LitterResource extends Resource
{
    protected static ?string $model = Litter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Breeding';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'litter_number';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['litter_number', 'sow.animal_number'];
    }

    public static function canCreate(): bool
    {
        return false; // litters are created by recording a farrowing
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sow', 'sire', 'farrowing', 'weaning']))
            ->columns([
                TextColumn::make('litter_number')->searchable()->sortable(),
                TextColumn::make('sow.animal_number')->label('Sow')->searchable(),
                TextColumn::make('sire.animal_number')->label('Sire')->placeholder('-'),
                TextColumn::make('born_on')->date()->sortable(),
                TextColumn::make('farrowing.born_alive')->label('Born alive'),
                TextColumn::make('weaning.weaned_count')->label('Weaned')->placeholder('-'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('expected_weaning_on')->label('Weaning due')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(LitterStatus::cases()))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('born_on', 'desc');
    }

    public static function getRelations(): array
    {
        return [PigletsRelationManager::class, LossesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListLitters::route('/'), 'view' => ViewLitter::route('/{record}')];
    }
}
