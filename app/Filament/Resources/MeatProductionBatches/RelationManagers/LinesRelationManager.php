<?php

namespace App\Filament\Resources\MeatProductionBatches\RelationManagers;

use App\Domain\Meat\Models\MeatProductionLine;
use App\Filament\Support\MoneyColumn;
use App\Support\Ratio;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Products made';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->columns([
                TextColumn::make('product.name')->label('Product'),
                TextColumn::make('weight_kg')->label('Weight (kg)'),
                MoneyColumn::make('cost_minor', 'Cost'),
                MoneyColumn::computed('per_kg', 'Cost per kg', fn (MeatProductionLine $r) => bccomp((string) $r->weight_kg, '0', 2) > 0 ? Ratio::toWhole(bcdiv((string) $r->cost_minor, (string) $r->weight_kg, 6)) : 0),
                TextColumn::make('use_by')->label('Use by')->date()->color(fn (MeatProductionLine $r) => $r->use_by->lt(now()->startOfDay()) ? 'danger' : null),
            ])
            ->paginated(false);
    }
}
