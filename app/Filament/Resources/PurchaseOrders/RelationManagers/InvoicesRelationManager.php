<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Filament\Resources\SupplierInvoices\SupplierInvoiceResource;
use App\Filament\Support\ProcurementActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices and payments';

    public function isReadOnly(): bool
    {
        return false;
    }

    #[On('purchase-order-changed')]
    public function refreshInvoices(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['supplier', 'order', 'payments']))
            ->columns(SupplierInvoiceResource::columns())
            ->recordActions([
                ProcurementActions::pay()->after(fn () => $this->dispatch('purchase-order-changed')),
                ProcurementActions::voidInvoice()->after(fn () => $this->dispatch('purchase-order-changed')),
            ])
            ->defaultSort('id', 'desc');
    }
}
