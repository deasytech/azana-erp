<?php

namespace App\Filament\Resources\InventoryBatches;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Filament\Resources\InventoryBatches\Pages\ListInventoryBatches;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Batches / lots with their expiry and supplier. They are created when stock is received. */
class InventoryBatchResource extends Resource
{
    protected static ?string $model = InventoryBatch::class;

    protected static ?string $navigationLabel = 'Batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 55;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item', 'supplier']))
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable(),
                TextColumn::make('item.name')->label('Item')->searchable(),
                TextColumn::make('supplier.name')->label('Supplier')->placeholder('-'),
                TextColumn::make('received_on')->date()->placeholder('-')->sortable(),
                TextColumn::make('expiry_date')->date()->placeholder('-')->sortable()
                    ->color(fn (InventoryBatch $r) => $r->isExpiredOn(now()) ? 'danger' : null),
                TextColumn::make('is_active')->label('Status')->badge()->formatStateUsing(fn ($state) => $state ? 'Active' : 'Blocked')->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('inventory_item_id')->label('Item')->relationship('item', 'name')->searchable()->preload(),
                Filter::make('expired')->label('Expired')->query(fn (Builder $query) => $query->whereDate('expiry_date', '<', now())),
            ])
            ->recordActions([
                Action::make('toggle')->label(fn (InventoryBatch $r) => $r->is_active ? 'Block' : 'Unblock')->color('warning')->requiresConfirmation()
                    ->modalDescription('A blocked batch (for example after a recall) cannot be received into or used until unblocked.')
                    ->visible(fn (InventoryBatch $r) => auth()->user()->can('update', $r))
                    ->action(fn (InventoryBatch $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->defaultSort('expiry_date');
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryBatches::route('/')];
    }
}
