<?php

namespace App\Filament\Resources\CashTransactions;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CashTransaction;
use App\Domain\Finance\Models\CostCentre;
use App\Enums\CashDirection;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\CashTransactions\Pages\CreateCashTransaction;
use App\Filament\Resources\CashTransactions\Pages\ListCashTransactions;
use App\Filament\Resources\ExpenseRecords\ExpenseRecordResource;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Money in or out of the cash and bank accounts that is not an invoice or an expense (a loan, a grant, a deposit...). */
class CashTransactionResource extends Resource
{
    protected static ?string $model = CashTransaction::class;

    protected static ?string $navigationLabel = 'Cash transactions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('transaction_date')->label('Date')->default(now())->maxDate(now())->required(),
            Select::make('direction')->options(AnimalResource::enumOptions(CashDirection::cases()))->required(),
            Select::make('cash_account_id')->label('Cash or bank account')->required()->options(fn () => Account::whereIn('system_key', ['cash', 'bank'])->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
            Select::make('counter_account_id')->label('Other account')->required()->searchable()->options(fn () => Account::where('is_active', true)->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['cash', 'bank']))->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
            MoneyInput::make('amount_minor', 'Amount')->required(),
            Select::make('cost_centre_id')->label('Cost centre')->searchable()->options(fn () => CostCentre::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
            TextInput::make('reference')->maxLength(60),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['cashAccount', 'counterAccount']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('direction')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state === CashDirection::In ? 'success' : 'warning'),
                TextColumn::make('cashAccount.name')->label('Account'),
                TextColumn::make('counterAccount.name')->label('For'),
                MoneyColumn::make('amount_minor', 'Amount'),
                TextColumn::make('voided_at')->label('Status')->badge()->state(fn (CashTransaction $r) => $r->isVoided() ? 'Voided' : 'Recorded')->color(fn ($state) => $state === 'Voided' ? 'danger' : 'success')
                    ->description(fn (CashTransaction $r) => $r->void_reason),
            ])
            ->filters([SelectFilter::make('direction')->options(AnimalResource::enumOptions(CashDirection::cases()))])
            ->recordActions([ExpenseRecordResource::void()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCashTransactions::route('/'), 'create' => CreateCashTransaction::route('/create')];
    }
}
