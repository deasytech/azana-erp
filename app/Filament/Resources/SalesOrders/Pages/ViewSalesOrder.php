<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Domain\Sales\Actions\CancelSalesOrder;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\DispatchSalesOrder;
use App\Domain\Sales\Actions\GetPickingList;
use App\Domain\Sales\Models\SalesOrder;
use App\Enums\SalesOrderStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\MoneyColumn;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewSalesOrder extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = SalesOrderResource::class;

    private function order(): SalesOrder
    {
        assert($this->record instanceof SalesOrder);

        return $this->record;
    }

    #[On('sales-order-changed')]
    public function refreshOrder(): void
    {
        $this->order()->refresh();
    }

    protected function afterStep(): void
    {
        $this->refreshOrder();
        $this->dispatch('sales-order-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('customer.name')->label('Customer')->url(fn (SalesOrder $o) => CustomerResource::getUrl('view', ['record' => $o->customer])),
                TextEntry::make('ordered_on')->date(),
                TextEntry::make('total_minor')->label('Total')->formatStateUsing(fn ($state) => MoneyColumn::format($state)),
                TextEntry::make('confirmedBy.name')->label('Confirmed by')->placeholder('-'),
                TextEntry::make('dispatched_on')->date()->placeholder('-'),
                TextEntry::make('dispatch_note')->placeholder('-'),
                TextEntry::make('invoice.number')->label('Invoice')->placeholder('-')->url(fn (SalesOrder $o) => $o->invoice ? InvoiceResource::getUrl('view', ['record' => $o->invoice]) : null),
                TextEntry::make('notes')->placeholder('-')->columnSpan(2),
                TextEntry::make('credit_warning')->label('Credit warning')->color('danger')->visible(fn (SalesOrder $o) => $o->credit_warning !== null)->columnSpanFull(),
                TextEntry::make('cancel_reason')->label('Cancelled because')->visible(fn (SalesOrder $o) => $o->cancel_reason !== null)->columnSpanFull(),
            ]),
            Section::make('Picking list')->description('What to fetch, and from where, to fill this order.')
                ->visible(fn (SalesOrder $o) => in_array($o->status, [Status::Confirmed, Status::Dispatched], true))
                ->schema([ViewEntry::make('picking')->hiddenLabel()->view('filament.sales.picking-list')->state(fn (SalesOrder $o) => app(GetPickingList::class)($o))]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $in = fn (Status ...$statuses) => in_array($this->order()->status, $statuses, true);
        $may = fn () => auth()->user()->can('update', $this->order());

        return [
            $this->step('confirm', 'Confirm', 'heroicon-o-check-circle', 'Order confirmed and stock reserved',
                fn () => app(ConfirmSalesOrder::class)($this->order(), auth()->user()), fn () => $in(Status::Draft) && $may(), [], 'success'),
            Action::make('dispatch')->label('Dispatch and invoice')->icon('heroicon-o-truck')->color('primary')->requiresConfirmation()
                ->modalDescription('The goods leave stock and the invoice is issued at the prices on this order.')
                ->visible(fn () => $in(Status::Confirmed) && $may())
                ->fillForm(fn () => ['dispatched_on' => now()->toDateString()])
                ->schema([
                    DatePicker::make('dispatched_on')->required()->maxDate(now()),
                    TextInput::make('dispatch_note')->label('Delivery note / vehicle')->maxLength(120),
                ])
                ->action(function (array $data, Action $action) {
                    $this->attempt(fn () => app(DispatchSalesOrder::class)($this->order(), Carbon::parse($data['dispatched_on']), $data['dispatch_note'] ?? null), $action);

                    $this->afterStep();
                    Notification::make()->title('Order dispatched and invoiced')->success()->send();
                }),
            $this->step('cancel', 'Cancel order', 'heroicon-o-trash', 'Order cancelled',
                fn (array $d) => app(CancelSalesOrder::class)($this->order(), $d['reason']), fn () => $in(Status::Draft, Status::Confirmed) && $may(),
                [Textarea::make('reason')->required()->helperText('Anything reserved for this order is released.')], 'danger'),
        ];
    }
}
