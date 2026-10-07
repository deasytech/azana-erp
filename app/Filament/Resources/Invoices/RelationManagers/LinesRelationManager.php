<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Domain\Sales\Models\InvoiceLine;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['semenBatch.boar', 'animal', 'productionBatch', 'meatLine.batch']))
            ->columns([
                TextColumn::make('description'),
                TextColumn::make('quantity')->numeric(decimalPlaces: 3)->suffix(fn (InvoiceLine $r) => " {$r->unit}"),
                MoneyColumn::make('unit_price_minor', 'Price'),
                TextColumn::make('discount_percent')->label('Discount')->suffix('%'),
                MoneyColumn::make('line_total_minor', 'Line total'),
                TextColumn::make('trace')->label('Traceable to')->state(fn (InvoiceLine $r) => match (true) {
                    $r->semen_batch_id !== null => "Batch {$r->semenBatch->number}, boar {$r->semenBatch->boar->animal_number}",
                    $r->meat_production_line_id !== null => "Meat batch {$r->meatLine->batch->number}",
                    $r->animal_id !== null => "Animal {$r->animal->animal_number}",
                    default => "Batch {$r->productionBatch?->code}",
                }),
            ])
            ->paginated(false);
    }
}
