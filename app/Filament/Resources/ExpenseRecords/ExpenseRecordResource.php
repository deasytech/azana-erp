<?php

namespace App\Filament\Resources\ExpenseRecords;

use App\Domain\Finance\Actions\VoidFinanceRecord;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\ExpenseRecord;
use App\Enums\AccountType;
use App\Filament\Resources\ExpenseRecords\Pages\CreateExpenseRecord;
use App\Filament\Resources\ExpenseRecords\Pages\ListExpenseRecords;
use App\Filament\Support\DomainAction;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\Action;
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

/** Costs of running the farm, charged to a cost centre. Recording one posts its journal entry; voiding reverses it. */
class ExpenseRecordResource extends Resource
{
    protected static ?string $model = ExpenseRecord::class;

    protected static ?string $navigationLabel = 'Expenses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('expense_date')->label('Date')->default(now())->maxDate(now())->required(),
            Select::make('account_id')->label('Expense account')->required()->searchable()->options(fn () => Account::where('is_active', true)->where('type', AccountType::Expense->value)->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
            Select::make('cost_centre_id')->label('Cost centre')->required()->searchable()->options(fn () => CostCentre::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
            MoneyInput::make('amount_minor', 'Amount')->required(),
            Select::make('paid_from_account_id')->label('Paid from')->searchable()->helperText('Leave empty if it has not been paid yet (it is then owed to the supplier).')
                ->options(fn () => Account::where('is_active', true)->whereIn('system_key', ['cash', 'bank'])->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
            TextInput::make('payee')->maxLength(255),
            TextInput::make('reference')->maxLength(60),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['account', 'costCentre']))
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('expense_date')->label('Date')->date()->sortable(),
                TextColumn::make('account.name')->label('Account'),
                TextColumn::make('costCentre.name')->label('Cost centre'),
                TextColumn::make('payee')->searchable()->placeholder('-'),
                MoneyColumn::make('amount_minor', 'Amount'),
                TextColumn::make('voided_at')->label('Status')->badge()->state(fn (ExpenseRecord $r) => $r->isVoided() ? 'Voided' : 'Recorded')->color(fn ($state) => $state === 'Voided' ? 'danger' : 'success')
                    ->description(fn (ExpenseRecord $r) => $r->void_reason),
            ])
            ->filters([SelectFilter::make('cost_centre_id')->label('Cost centre')->options(fn () => CostCentre::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())])
            ->recordActions([static::void()])
            ->defaultSort('id', 'desc');
    }

    public static function void(): Action
    {
        return Action::make('void')->label('Void')->color('danger')->requiresConfirmation()
            ->visible(fn ($record) => ! $record->isVoided() && auth()->user()->can('update', $record))
            ->schema([Textarea::make('reason')->required()])
            ->action(fn ($record, array $data, Action $action) => DomainAction::run(fn () => app(VoidFinanceRecord::class)($record, $data['reason']), $action, 'Voided'));
    }

    public static function getPages(): array
    {
        return ['index' => ListExpenseRecords::route('/'), 'create' => CreateExpenseRecord::route('/create')];
    }
}
