<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('customer.name')->label('Customer')->url(fn (Invoice $i) => CustomerResource::getUrl('view', ['record' => $i->customer])),
                TextEntry::make('order.number')->label('Order')->url(fn (Invoice $i) => SalesOrderResource::getUrl('view', ['record' => $i->order])),
                TextEntry::make('currency_code')->label('Currency'),
                TextEntry::make('issued_on')->date(),
                TextEntry::make('due_on')->date()->color(fn (Invoice $i) => $i->isOverdue() ? 'danger' : null),
                TextEntry::make('total_minor')->label('Total')->formatStateUsing(fn ($state) => MoneyColumn::format($state)),
                TextEntry::make('owing')->label('Still owing')->state(fn (Invoice $i) => MoneyColumn::format($i->balanceMinor())),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive_payment')->label('Receive payment')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => $this->record->balanceMinor() > 0 && auth()->user()->can('create', Payment::class))
                ->url(fn () => CustomerPaymentResource::getUrl('create', ['customer' => $this->record->customer_id, 'invoice' => $this->record->id])),
        ];
    }
}
