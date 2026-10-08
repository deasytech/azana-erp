<?php

namespace App\Filament\Resources\FeedProductionBatches;

use App\Domain\Feed\Models\FeedProductionBatch;
use App\Filament\Resources\FeedProductionBatches\Pages\ListFeedProductionBatches;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\MoneyColumn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Finished feed batches and what each cost to make. Created by completing a production order. */
class FeedProductionBatchResource extends Resource
{
    protected static ?string $model = FeedProductionBatch::class;

    protected static ?string $navigationLabel = 'Finished batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Feed mill';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.formula', 'item', 'inventoryBatch', 'location']))
            ->columns([
                TextColumn::make('order.number')->label('Order')->searchable(),
                TextColumn::make('inventoryBatch.batch_number')->label('Batch')->placeholder('-')->searchable(),
                TextColumn::make('item.name')->label('Feed'),
                TextColumn::make('order.formula.name')->label('Formula'),
                TextColumn::make('produced_on')->date()->sortable(),
                TextColumn::make('output_kg')->label('Made (kg)')->numeric(decimalPlaces: 3),
                MoneyColumn::make('material_cost_minor', 'Materials'),
                MoneyColumn::make('other_cost_minor', 'Other costs'),
                MoneyColumn::make('total_cost_minor', 'Total cost'),
                MoneyColumn::make('cost_per_kg_minor', 'Cost per kg'),
                IconColumn::make('reversed')->label('Reversed')->boolean()->getStateUsing(fn (FeedProductionBatch $r) => $r->isReversed()),
            ])
            ->filters([DateRangeFilter::make('produced_on', 'Produced')])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListFeedProductionBatches::route('/')];
    }
}
