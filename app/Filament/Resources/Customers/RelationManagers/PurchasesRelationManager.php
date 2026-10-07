<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Sales\Models\InvoiceLine;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Everything the customer has bought, line by line, with the batch it came from. */
class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceLines';

    protected static ?string $title = 'Items bought';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['invoice', 'semenBatch', 'animal', 'productionBatch', 'meatLine.batch']))
            ->columns([
                TextColumn::make('invoice.issued_on')->label('Date')->date()->sortable(),
                TextColumn::make('invoice.number')->label('Invoice'),
                TextColumn::make('description'),
                TextColumn::make('quantity')->numeric(decimalPlaces: 3)->suffix(fn (InvoiceLine $r) => " {$r->unit}"),
                MoneyColumn::make('unit_price_minor', 'Price'),
                MoneyColumn::make('line_total_minor', 'Total'),
                TextColumn::make('source')->label('From')->state(fn (InvoiceLine $r) => match (true) {
                    $r->semen_batch_id !== null => $r->semenBatch->number,
                    $r->meat_production_line_id !== null => $r->meatLine->batch->number,
                    $r->animal_id !== null => $r->animal->animal_number,
                    default => $r->productionBatch?->code,
                }),
            ])
            ->defaultSort('id', 'desc');
    }
}
