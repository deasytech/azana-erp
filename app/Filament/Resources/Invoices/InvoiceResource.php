<?php

namespace App\Filament\Resources\Invoices;

use App\Domain\Sales\Models\Invoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\AllocationsRelationManager;
use App\Filament\Resources\Invoices\RelationManagers\LinesRelationManager;
use App\Filament\Support\MoneyColumn;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** What customers have been invoiced and what is still owed. Invoices are issued when an order is dispatched. */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return list<Column> */
    public static function columns(): array
    {
        return [
            TextColumn::make('number')->searchable()->sortable(),
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('issued_on')->date()->sortable(),
            TextColumn::make('due_on')->date()->sortable()->color(fn (Invoice $r) => $r->isOverdue() ? 'danger' : null),
            MoneyColumn::make('total_minor', 'Total'),
            MoneyColumn::computed('balance', 'Owing', fn (Invoice $r) => $r->balanceMinor()),
            TextColumn::make('status')->badge()->state(fn (Invoice $r) => match (true) {
                $r->balanceMinor() === 0 => 'Paid', $r->isOverdue() => 'Overdue', $r->paidMinor() > 0 => 'Part paid', default => 'Open',
            })->color(fn (string $state) => match ($state) {
                'Paid' => 'success', 'Overdue' => 'danger', default => 'warning',
            }),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'allocations.payment']))
            ->columns(static::columns())
            ->filters([
                SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->searchable()->preload(),
                Filter::make('owing')->label('Still owing')->default()->query(fn (Builder $query) => $query
                    ->whereRaw('total_minor > (select coalesce(sum(a.amount_minor), 0) from payment_allocations a join payments p on p.id = a.payment_id where a.invoice_id = invoices.id and p.voided_at is null)')),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('due_on');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class, AllocationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/'), 'view' => ViewInvoice::route('/{record}')];
    }
}
