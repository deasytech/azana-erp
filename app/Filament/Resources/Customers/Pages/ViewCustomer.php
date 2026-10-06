<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Domain\Sales\Actions\GetCustomerAccount;
use App\Domain\Sales\Actions\SetCustomerCredit;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Payment;
use App\Enums\CreditStatus;
use App\Filament\Concerns\HasWorkflowSteps;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ViewCustomer extends ViewRecord
{
    use HasWorkflowSteps;

    protected static string $resource = CustomerResource::class;

    /** @var array<string, mixed>|null */
    protected ?array $accountMemo = null;

    private function customer(): Customer
    {
        assert($this->record instanceof Customer);

        return $this->record;
    }

    private function account(): array
    {
        return $this->accountMemo ??= app(GetCustomerAccount::class)($this->customer());
    }

    #[On('customer-account-changed')]
    public function refreshAccount(): void
    {
        $this->customer()->refresh();
        $this->accountMemo = null;
    }

    protected function afterStep(): void
    {
        $this->refreshAccount();
        $this->dispatch('customer-account-changed');
    }

    public function infolist(Schema $schema): Schema
    {
        $money = fn (string $key) => fn () => MoneyColumn::format($this->account()[$key]);

        return $schema->components([
            Section::make('Customer')->columns(4)->schema([
                TextEntry::make('code')->weight('bold')->copyable(),
                TextEntry::make('name'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('phone')->placeholder('-'),
                TextEntry::make('email')->placeholder('-'),
                TextEntry::make('address')->placeholder('-')->columnSpan(2),
                TextEntry::make('notes')->placeholder('-'),
            ]),
            Section::make('Account')->columns(4)->schema([
                TextEntry::make('owed')->label('Owes')->state($money('outstanding')),
                TextEntry::make('overdue')->label('Overdue')->state($money('overdue'))->color(fn () => $this->account()['overdue'] > 0 ? 'danger' : null),
                TextEntry::make('deposit')->label('On deposit')->state($money('deposit')),
                TextEntry::make('headroom')->label('Credit still available')->state($money('headroom'))->color(fn () => $this->account()['headroom'] < 0 ? 'danger' : null),
                TextEntry::make('credit_status')->label('Credit')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('credit_limit_minor')->label('Credit limit')->formatStateUsing(fn ($state) => MoneyColumn::format($state)),
                TextEntry::make('payment_terms_days')->label('Payment terms')->suffix(' days'),
                TextEntry::make('creditApprover.name')->label('Credit approved by')->placeholder('-'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('receive_payment')->label('Receive payment')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => auth()->user()->can('create', Payment::class))
                ->url(fn () => CustomerPaymentResource::getUrl('create', ['customer' => $this->customer()->id])),
            $this->step('credit', 'Set credit', 'heroicon-o-credit-card', 'Credit updated',
                fn (array $d) => app(SetCustomerCredit::class)($this->customer(), CreditStatus::from($d['status']), (int) ($d['limit'] ?? 0), (int) $d['terms'], auth()->user()),
                fn () => auth()->user()->can('approve', $this->customer()),
                [
                    Select::make('status')->options(AnimalResource::enumOptions(CreditStatus::cases()))->required()->default(fn () => $this->customer()->credit_status->value)
                        ->helperText('On hold = no new credit until released; blocked = no sales at all.'),
                    MoneyInput::make('limit', 'Credit limit')->default(fn () => $this->customer()->credit_limit_minor)->required(),
                    TextInput::make('terms')->label('Payment terms (days)')->numeric()->integer()->minValue(0)->maxValue(365)->required()->default(fn () => $this->customer()->payment_terms_days),
                ], 'warning'),
        ];
    }
}
