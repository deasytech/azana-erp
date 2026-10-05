<?php

namespace App\Filament\Resources\SupplierInvoices;

use App\Domain\Procurement\Models\SupplierInvoice;
use App\Filament\Resources\SupplierInvoices\Pages\ListSupplierInvoices;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\ProcurementActions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** What suppliers have invoiced and how much is still owed. Invoices are recorded from the purchase order. */
class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $navigationLabel = 'Supplier invoices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['supplier', 'order', 'payments']))
            ->columns(static::columns())
            ->filters([
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
                Filter::make('owing')->label('Still owing')->default()->query(fn (Builder $query) => $query->whereNull('voided_at')
                    ->whereRaw('total_minor > (select coalesce(sum(amount_minor), 0) from supplier_payments where supplier_payments.supplier_invoice_id = supplier_invoices.id and supplier_payments.status = ?)', ['paid'])),
            ])
            ->recordActions([ProcurementActions::pay(), ProcurementActions::voidInvoice()])
            ->defaultSort('due_date');
    }

    /** @return list<Column> shared with the order page's invoices tab */
    public static function columns(): array
    {
        return [
            TextColumn::make('number')->searchable()->sortable(),
            TextColumn::make('invoice_number')->label('Supplier invoice')->searchable(),
            TextColumn::make('supplier.name')->label('Supplier'),
            TextColumn::make('order.number')->label('Order'),
            TextColumn::make('invoice_date')->date()->sortable(),
            TextColumn::make('due_date')->date()->sortable()->color(fn (SupplierInvoice $r) => $r->isOverdue() ? 'danger' : null),
            MoneyColumn::make('total_minor', 'Total'),
            MoneyColumn::computed('paid', 'Paid', fn (SupplierInvoice $r) => $r->paidMinor()),
            MoneyColumn::computed('balance', 'Balance', fn (SupplierInvoice $r) => $r->balanceMinor()),
            TextColumn::make('status')->badge()->state(fn (SupplierInvoice $r) => match (true) {
                $r->isVoided() => 'Voided', $r->balanceMinor() === 0 => 'Paid', $r->isOverdue() => 'Overdue', $r->paidMinor() > 0 => 'Part paid', default => 'Open',
            })->color(fn (string $state) => match ($state) {
                'Paid' => 'success', 'Overdue' => 'danger', 'Voided' => 'gray', default => 'warning',
            }),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListSupplierInvoices::route('/')];
    }
}
