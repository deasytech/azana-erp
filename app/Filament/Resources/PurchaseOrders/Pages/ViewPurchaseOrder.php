<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\GetPurchaseTrace;
use App\Domain\Procurement\Actions\MatchSupplierInvoices;
use App\Domain\Procurement\Actions\ReceiveGoods;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Enums\PurchaseOrderStatus as Status;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\ProgressSteps;
use App\Filament\Widgets\PurchaseOrderProgressChartWidget;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewPurchaseOrder extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = PurchaseOrderResource::class;

    /** @var array<string, mixed>|null */
    protected ?array $traceMemo = null;

    private function order(): PurchaseOrder
    {
        assert($this->record instanceof PurchaseOrder);

        return $this->record;
    }

    /** Request-to-payment figures, computed once per request. */
    private function trace(): array
    {
        return $this->traceMemo ??= app(GetPurchaseTrace::class)($this->order());
    }

    /** Also called when a tab changes the order (a receipt voided, an invoice paid...). */
    #[On('purchase-order-changed')]
    public function refreshOrder(): void
    {
        $this->order()->refresh();
        $this->traceMemo = null;
    }

    /** The order's journey, read from its status and the trace figures; the rules for each step live in the domain actions. @return list<array<string, mixed>> */
    private function progress(): array
    {
        $order = $this->order();

        if ($order->status === Status::Rejected || $order->status === Status::Cancelled) {
            return ProgressSteps::stopped(['Drafted'], $order->status->label());
        }

        $trace = $this->trace();
        $done = match ($order->status) {
            Status::Draft, Status::PendingApproval => 1,
            Status::Approved, Status::PartiallyReceived => 2,
            default => 3,
        };
        $done = $done === 3 && (int) $trace['invoiced'] > 0 ? 4 : $done;
        $done = $done === 4 && (int) $trace['paid'] >= (int) $trace['invoiced'] ? 5 : $done;

        return ProgressSteps::make(
            ['Drafted', 'Approved', 'Received', 'Invoiced', 'Paid'],
            $done,
            [1 => $order->status === Status::PendingApproval ? 'Waiting for approval' : null, 2 => $order->status === Status::PartiallyReceived ? 'Part received' : null],
        );
    }

    /** Ordered, received, invoiced and paid drawn as bars, below the order's sections. */
    protected function getFooterWidgets(): array
    {
        return [PurchaseOrderProgressChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    protected function afterStep(): void
    {
        $this->refreshOrder();
        $this->dispatch('purchase-order-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Progress')->description('From request to payment.')->schema([
                ViewEntry::make('progress')->hiddenLabel()->view('filament.pages.partials.steps-entry')->state(fn () => $this->progress()),
            ]),
            Section::make('Order')->columns(4)->schema([
                TextEntry::make('number')->weight('bold')->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('supplier.name')->label('Supplier'),
                TextEntry::make('ordered_on')->date(),
                TextEntry::make('expected_on')->date()->placeholder('-'),
                TextEntry::make('payment_terms_days')->label('Payment terms')->suffix(' days'),
                TextEntry::make('total_minor')->label('Order total')->formatStateUsing(fn ($state) => MoneyColumn::format($state)),
                TextEntry::make('request.number')->label('From request')->placeholder('-')
                    ->url(fn (PurchaseOrder $o) => $o->request ? PurchaseRequestResource::getUrl('view', ['record' => $o->request]) : null),
                TextEntry::make('createdBy.name')->label('Raised by')->placeholder('-'),
                TextEntry::make('decidedBy.name')->label('Decided by')->placeholder('-'),
                TextEntry::make('decision_notes')->label('Decision notes')->placeholder('-'),
                TextEntry::make('notes')->placeholder('-'),
            ]),
            Section::make('Delivery and payment')->columns(3)->schema([
                TextEntry::make('received_value')->label('Received so far')->state(fn () => MoneyColumn::format($this->trace()['received_value'])),
                TextEntry::make('invoiced')->label('Invoiced')->state(fn () => MoneyColumn::format($this->trace()['invoiced'])),
                TextEntry::make('paid')->label('Paid')->state(fn () => MoneyColumn::format($this->trace()['paid'])),
            ]),
        ]);
    }

    public function getSubheading(): ?string
    {
        return $this->order()->supplier->name.' - ordered '.$this->order()->ordered_on->format('d M Y');
    }

    protected function getHeaderActions(): array
    {
        $in = fn (Status ...$statuses) => in_array($this->order()->status, $statuses, true);
        $decide = app(DecidePurchaseOrder::class);

        return [
            $this->step('submit', 'Submit', 'heroicon-o-paper-airplane', 'Order submitted',
                fn () => $decide->submit($this->order()), fn () => $in(Status::Draft) && auth()->user()->can('create', PurchaseOrder::class)),
            $this->step('approve', 'Approve', 'heroicon-o-check-circle', 'Order approved',
                fn (array $d) => $decide->approve($this->order(), auth()->user(), $d['notes'] ?? null),
                fn () => $in(Status::PendingApproval) && auth()->user()->can('approve', $this->order()), [Textarea::make('notes')], 'success'),
            $this->step('reject', 'Reject', 'heroicon-o-x-circle', 'Order rejected',
                fn (array $d) => $decide->reject($this->order(), auth()->user(), $d['reason']),
                fn () => $in(Status::PendingApproval) && auth()->user()->can('approve', $this->order()), [Textarea::make('reason')->required()], 'danger'),
            $this->step('cancel', 'Cancel order', 'heroicon-o-trash', 'Order cancelled',
                fn () => $decide->cancel($this->order()),
                fn () => $in(Status::Draft, Status::PendingApproval, Status::Approved) && auth()->user()->can('update', $this->order()), [], 'gray'),
            $this->receiveAction(),
            $this->invoiceAction(),
        ];
    }

    private function receiveAction(): Action
    {
        return Action::make('receive')->label('Receive goods')->icon('heroicon-o-inbox-arrow-down')
            ->visible(fn () => $this->order()->status->canReceive() && auth()->user()->can('create', GoodsReceipt::class))
            ->fillForm(fn () => ['received_on' => now()->toDateString(), 'lines' => $this->outstandingLines()])
            ->schema([
                Select::make('inventory_location_id')->label('Store')->required()->searchable()
                    ->options(fn () => InventoryLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('received_on')->default(now())->maxDate(now())->required(),
                TextInput::make('delivery_note')->maxLength(60),
                Textarea::make('notes'),
                Repeater::make('lines')->label('Items delivered')->addable(false)->reorderable(false)->columnSpanFull()->columns(4)
                    ->helperText('Leave the quantity empty for items that did not arrive.')
                    ->schema([
                        Hidden::make('purchase_order_line_id'), Hidden::make('tracks_batches'), Hidden::make('tracks_expiry'),
                        TextInput::make('item')->label('Item')->disabled()->dehydrated(false),
                        TextInput::make('quantity')->numeric()->minValue(0)->step(0.001),
                        TextInput::make('batch_number')->visible(fn ($get) => (bool) $get('tracks_batches'))->required(fn ($get) => $get('tracks_batches') && filled($get('quantity'))),
                        DatePicker::make('expiry_date')->visible(fn ($get) => (bool) $get('tracks_expiry'))->required(fn ($get) => $get('tracks_expiry') && filled($get('quantity'))),
                    ]),
            ])
            ->action(function (array $data, Action $action) {
                $lines = collect($data['lines'] ?? [])->filter(fn ($l) => filled($l['quantity'] ?? null) && bccomp((string) $l['quantity'], '0', 3) > 0)
                    ->map(fn ($l) => ['purchase_order_line_id' => (int) $l['purchase_order_line_id'], 'quantity' => (string) $l['quantity'],
                        'batch_number' => $l['batch_number'] ?? null, 'expiry_date' => $l['expiry_date'] ?? null])->values()->all();

                $this->attempt(fn () => app(ReceiveGoods::class)($this->order(), (int) $data['inventory_location_id'], Carbon::parse($data['received_on']), $lines, $data['delivery_note'] ?? null, $data['notes'] ?? null), $action);

                $this->afterStep();
                Notification::make()->title('Goods received')->success()->send();
            });
    }

    /** @return list<array<string, mixed>> the order lines that still have something to receive */
    private function outstandingLines(): array
    {
        return $this->order()->lines()->with('item.unit')->get()->map(function ($line) {
            $left = bcsub((string) $line->quantity, $line->receivedQuantity(), 3);

            return bccomp($left, '0', 3) > 0 ? [
                'purchase_order_line_id' => $line->id, 'tracks_batches' => $line->item->tracks_batches, 'tracks_expiry' => $line->item->tracks_expiry,
                'item' => "{$line->item->name} (outstanding {$left} {$line->item->unit->code})", 'quantity' => $left,
            ] : null;
        })->filter()->values()->all();
    }

    private function invoiceAction(): Action
    {
        return Action::make('invoice')->label('Record invoice')->icon('heroicon-o-receipt-percent')
            ->visible(fn () => in_array($this->order()->status, [Status::PartiallyReceived, Status::Received], true) && auth()->user()->can('create', SupplierInvoice::class))
            ->fillForm(fn () => ['invoice_date' => now()->toDateString(), 'subtotal_minor' => max(0, app(MatchSupplierInvoices::class)->uninvoiced($this->order()))])
            ->schema([
                TextInput::make('invoice_number')->label('Supplier\'s invoice number')->required()->maxLength(60),
                DatePicker::make('invoice_date')->maxDate(now())->required(),
                MoneyInput::make('subtotal_minor', 'Goods amount')->required()->helperText('At most what has been received and not yet invoiced.'),
                MoneyInput::make('tax_minor', 'Tax'),
                Textarea::make('notes'),
            ])
            ->action(function (array $data, Action $action) {
                $this->attempt(fn () => app(RecordSupplierInvoice::class)($this->order(), $data['invoice_number'], Carbon::parse($data['invoice_date']), (int) $data['subtotal_minor'], (int) ($data['tax_minor'] ?? 0), $data['notes'] ?? null), $action);

                $this->afterStep();
                Notification::make()->title('Invoice recorded')->success()->send();
            });
    }
}
