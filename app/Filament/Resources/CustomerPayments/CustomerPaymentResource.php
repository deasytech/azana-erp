<?php

namespace App\Filament\Resources\CustomerPayments;

use App\Domain\Sales\Actions\VoidCustomerPayment;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Enums\ReceiptMethod;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\CustomerPayments\Pages\CreateCustomerPayment;
use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
use App\Filament\Support\DomainAction;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Money received from customers. Anything not put against an invoice stays with the customer as a deposit. */
class CustomerPaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationLabel = 'Payments received';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')->description('Who paid, how much and how.')->columnSpanFull()->columns(2)->schema([
                Select::make('customer_id')->label('Customer')->required()->searchable()->live()
                    ->options(fn () => Customer::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->code} - {$c->name}"])->all()),
                MoneyInput::make('amount_minor', 'Amount received')->required(),
                Select::make('method')->options(AnimalResource::enumOptions(ReceiptMethod::cases()))->required()->default(ReceiptMethod::BankTransfer->value),
                DatePicker::make('received_on')->default(now())->maxDate(now())->required(),
                TextInput::make('reference')->maxLength(60),
            ]),
            Section::make('Allocation')->description('Which invoices this payment settles.')->columnSpanFull()->columns(2)->schema([
                Select::make('apply')->label('Apply to')->options([
                    'oldest' => 'The oldest invoices first',
                    'invoices' => 'Invoices I choose',
                    'deposit' => 'Keep as a deposit',
                ])->default('oldest')->required()->live(),
                Textarea::make('notes')->columnSpanFull(),
                Repeater::make('invoices')->label('Invoices')->columnSpanFull()->columns(2)->visible(fn ($get) => $get('apply') === 'invoices')->addActionLabel('Add invoice')
                    ->schema([
                        Select::make('invoice_id')->label('Invoice')->required()->distinct()
                            ->options(fn ($get) => Invoice::where('customer_id', $get('../../customer_id'))->with('allocations.payment')->orderBy('issued_on')->get()
                                ->filter(fn (Invoice $i) => $i->balanceMinor() > 0)->mapWithKeys(fn (Invoice $i) => [$i->id => "{$i->number} (owes ".MoneyColumn::format($i->balanceMinor()).')'])->all()),
                        MoneyInput::make('amount_minor', 'Amount')->required(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'allocations']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('received_on')->date()->sortable(),
                MoneyColumn::make('amount_minor', 'Amount'),
                TextColumn::make('method')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('reference')->placeholder('-'),
                MoneyColumn::computed('deposit', 'Left as deposit', fn (Payment $r) => $r->unallocatedMinor()),
                IconColumn::make('voided')->label('Voided')->boolean()->getStateUsing(fn (Payment $r) => $r->isVoided()),
            ])
            ->filters([
                SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->searchable()->preload(),
                SelectFilter::make('method')->options(AnimalResource::enumOptions(ReceiptMethod::cases())),
            ])
            ->recordActions([
                Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
                    ->modalDescription('The invoices this payment settled owe their money again.')
                    ->visible(fn (Payment $r) => ! $r->isVoided() && auth()->user()->can('update', $r))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (Payment $record, array $data, Action $action) => DomainAction::run(
                        fn () => app(VoidCustomerPayment::class)($record, $data['reason']), $action, 'Payment voided')),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerPayments::route('/'), 'create' => CreateCustomerPayment::route('/create')];
    }
}
