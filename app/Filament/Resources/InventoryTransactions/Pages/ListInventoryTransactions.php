<?php

namespace App\Filament\Resources\InventoryTransactions\Pages;

use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryTransactions extends ListRecords
{
    protected static string $resource = InventoryTransactionResource::class;
}
