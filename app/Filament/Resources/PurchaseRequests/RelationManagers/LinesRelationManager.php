<?php

namespace App\Filament\Resources\PurchaseRequests\RelationManagers;

use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items requested';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('item.unit'))
            ->columns([
                TextColumn::make('item.name')->label('Item'),
                TextColumn::make('quantity')->numeric(decimalPlaces: 3)->suffix(fn ($record) => ' '.$record->item->unit->code),
                MoneyColumn::make('estimated_unit_cost_minor', 'Estimated cost per unit'),
                TextColumn::make('notes')->placeholder('-'),
            ])
            ->paginated(false);
    }
}
