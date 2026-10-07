<?php

namespace App\Filament\Resources\Budgets;

use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\CostCentre;
use App\Enums\AccountType;
use App\Enums\BudgetStatus;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Support\DomainAction;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** A year's plan of revenue and expenses by account, cost centre and month. Locked once approved. */
class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $navigationLabel = 'Budgets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all();

        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('fiscal_year')->numeric()->integer()->minValue(2000)->maxValue(2100)->default((int) now()->year)->required(),
            Textarea::make('notes')->columnSpanFull(),
            Repeater::make('lines')->columnSpanFull()->minItems(1)->columns(4)->addActionLabel('Add line')
                ->schema([
                    Select::make('account_id')->label('Account')->required()->searchable()
                        ->options(fn () => Account::where('is_active', true)->whereIn('type', [AccountType::Revenue->value, AccountType::Expense->value])->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
                    Select::make('cost_centre_id')->label('Cost centre')->searchable()->options(fn () => CostCentre::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('month')->options($months)->required(),
                    MoneyInput::make('amount_minor', 'Amount')->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('fiscal_year')->label('Year')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state === BudgetStatus::Approved ? 'success' : 'gray'),
                TextColumn::make('approvedBy.name')->label('Approved by')->placeholder('-'),
            ])
            ->recordActions([EditAction::make()->visible(fn (Budget $r) => ! $r->isApproved()), static::approve()])
            ->defaultSort('fiscal_year', 'desc');
    }

    public static function approve(): Action
    {
        return Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')->requiresConfirmation()
            ->modalDescription('An approved budget is locked: make a new one to change the plan.')
            ->visible(fn (Budget $r) => ! $r->isApproved() && auth()->user()->can('approve', $r))
            ->action(fn (Budget $record, Action $action) => DomainAction::run(fn () => app(SaveBudget::class)->approve($record, auth()->user()), $action, 'Budget approved'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }
}
