<?php

namespace App\Filament\Support;

use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use Filament\Forms\Components\Select;

/** Form pieces shared by the stock screens. */
class StockForms
{
    public static function item(string $field = 'inventory_item_id', string $label = 'Item'): Select
    {
        return Select::make($field)->label($label)->required()->searchable()->live()
            ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->code} - {$i->name}"])->all());
    }

    public static function store(string $field = 'inventory_location_id', string $label = 'Store'): Select
    {
        return Select::make($field)->label($label)->required()->searchable()
            ->options(fn () => InventoryLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id'));
    }

    /** Batches of the item chosen in the form, soonest expiry first. */
    public static function batch(string $field = 'inventory_batch_id', string $itemField = 'inventory_item_id'): Select
    {
        return Select::make($field)->label('Batch')->searchable()
            ->visible(fn ($get) => static::tracksBatches($get($itemField)))
            ->options(fn ($get) => InventoryBatch::where('inventory_item_id', $get($itemField))->where('is_active', true)
                ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->get()
                ->mapWithKeys(fn ($b) => [$b->id => $b->batch_number.($b->expiry_date ? ' (expires '.$b->expiry_date->format('d M Y').')' : '')])->all());
    }

    public static function tracksBatches(mixed $itemId): bool
    {
        return $itemId && InventoryItem::whereKey($itemId)->where('tracks_batches', true)->exists();
    }

    public static function tracksExpiry(mixed $itemId): bool
    {
        return $itemId && InventoryItem::whereKey($itemId)->where('tracks_expiry', true)->exists();
    }
}
