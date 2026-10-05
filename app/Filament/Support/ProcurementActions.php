<?php

namespace App\Filament\Support;

use App\Domain\Procurement\Actions\DecideSupplierPayment;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Procurement\Actions\VoidGoodsReceipt;
use App\Domain\Procurement\Actions\VoidSupplierInvoice;
use App\Domain\Procurement\Models\GoodsReceipt;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Animals\AnimalResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Row actions on receipts, invoices and payments, shared by their own lists and the order page's tabs. */
class ProcurementActions
{
    public static function voidReceipt(): Action
    {
        return Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
            ->modalDescription('Takes the delivery back out of stock. Refused if the goods were used or are already invoiced.')
            ->visible(fn (GoodsReceipt $r) => ! $r->isVoided() && auth()->user()->can('update', $r))
            ->schema([Textarea::make('reason')->required()])
            ->action(fn (GoodsReceipt $record, array $data, Action $action) => DomainAction::run(
                fn () => app(VoidGoodsReceipt::class)($record, $data['reason']), $action, 'Goods receipt voided'));
    }

    public static function pay(): Action
    {
        return Action::make('pay')->label('Pay')->icon('heroicon-o-banknotes')->color('success')
            ->visible(fn (SupplierInvoice $i) => ! $i->isVoided() && $i->total_minor > $i->committedMinor() && auth()->user()->can('create', SupplierPayment::class))
            ->fillForm(fn (SupplierInvoice $i) => ['amount_minor' => $i->total_minor - $i->committedMinor()])
            ->schema([
                MoneyInput::make('amount_minor', 'Amount')->required(),
                DatePicker::make('paid_on')->default(now())->maxDate(now())->required(),
                Select::make('method')->options(AnimalResource::enumOptions(PaymentMethod::cases()))->required()->default(PaymentMethod::BankTransfer->value),
                TextInput::make('reference')->maxLength(60),
            ])
            ->action(function (SupplierInvoice $record, array $data, Action $action) {
                try {
                    $payment = app(RecordSupplierPayment::class)($record, (int) $data['amount_minor'], Carbon::parse($data['paid_on']), PaymentMethod::from($data['method']), $data['reference'] ?? null);
                } catch (DomainException $e) {
                    Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
                    $action->halt();
                }

                Notification::make()->title($payment->status === PaymentStatus::PendingApproval ? 'Payment held for approval' : 'Payment recorded')->success()->send();
            });
    }

    public static function voidInvoice(): Action
    {
        return Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
            ->visible(fn (SupplierInvoice $i) => ! $i->isVoided() && auth()->user()->can('update', $i))
            ->schema([Textarea::make('reason')->required()])
            ->action(fn (SupplierInvoice $record, array $data, Action $action) => DomainAction::run(
                fn () => app(VoidSupplierInvoice::class)($record, $data['reason']), $action, 'Invoice voided'));
    }

    /** @return list<Action> */
    public static function paymentDecisions(): array
    {
        $pending = fn (SupplierPayment $p) => $p->status === PaymentStatus::PendingApproval && auth()->user()->can('approve', $p);

        return [
            Action::make('approve')->label('Approve')->color('success')->requiresConfirmation()->visible($pending)
                ->schema([Textarea::make('notes')])
                ->action(fn (SupplierPayment $record, array $data, Action $action) => DomainAction::run(
                    fn () => app(DecideSupplierPayment::class)->approve($record, auth()->user(), $data['notes'] ?? null), $action, 'Payment approved')),
            Action::make('reject')->label('Reject')->color('danger')->visible($pending)
                ->schema([Textarea::make('reason')->required()])
                ->action(fn (SupplierPayment $record, array $data, Action $action) => DomainAction::run(
                    fn () => app(DecideSupplierPayment::class)->reject($record, auth()->user(), $data['reason']), $action, 'Payment rejected')),
            Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
                ->visible(fn (SupplierPayment $p) => $p->status === PaymentStatus::Paid && auth()->user()->can('update', $p))
                ->schema([Textarea::make('reason')->required()])
                ->action(fn (SupplierPayment $record, array $data, Action $action) => DomainAction::run(
                    fn () => app(DecideSupplierPayment::class)->void($record, $data['reason']), $action, 'Payment voided')),
        ];
    }
}
