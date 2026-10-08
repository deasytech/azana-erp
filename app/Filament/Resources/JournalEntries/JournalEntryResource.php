<?php

namespace App\Filament\Resources\JournalEntries;

use App\Domain\Finance\Actions\DecideJournal;
use App\Domain\Finance\Actions\ReverseJournal;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Finance\Models\JournalEntry;
use App\Enums\JournalStatus;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Filament\Support\DomainAction;
use App\Filament\Support\MoneyColumn;
use App\Filament\Support\MoneyInput;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** The journal: sales, receipts and purchases arrive from the operational modules; manual entries wait for an approver. */
class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationLabel = 'Journal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entry')->description('When and why.')->columns(2)->schema([
                DatePicker::make('entry_date')->label('Date')->default(now())->maxDate(now())->required(),
                TextInput::make('description')->required()->maxLength(255),
            ]),
            Section::make('Lines')->description('Debits and credits must balance.')->schema([
                Repeater::make('lines')->hiddenLabel()->columnSpanFull()->minItems(2)->columns(4)->addActionLabel('Add line')
                    ->schema([
                        Select::make('account_id')->label('Account')->required()->searchable()->options(fn () => Account::where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all()),
                        Select::make('cost_centre_id')->label('Cost centre')->searchable()->options(fn () => CostCentre::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        MoneyInput::make('debit_minor', 'Debit'),
                        MoneyInput::make('credit_minor', 'Credit'),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('entry_date')->label('Date')->date()->sortable(),
                TextColumn::make('description')->searchable()->limit(60),
                MoneyColumn::make('total_minor', 'Amount'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state) {
                        JournalStatus::Posted => 'success', JournalStatus::Pending => 'warning', default => 'danger'
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(AnimalResource::enumOptions(JournalStatus::cases()))])
            ->recordActions([ViewAction::make(), static::approve(), static::reject(), static::reverse()])
            ->defaultSort('id', 'desc');
    }

    public static function approve(): Action
    {
        return Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')->requiresConfirmation()
            ->visible(fn (JournalEntry $r) => $r->status === JournalStatus::Pending && auth()->user()->can('approve', $r))
            ->action(fn (JournalEntry $record, Action $action) => DomainAction::run(fn () => app(DecideJournal::class)->approve($record, auth()->user()), $action, 'Entry posted'));
    }

    public static function reject(): Action
    {
        return Action::make('reject')->label('Reject')->icon('heroicon-o-x-mark')->color('danger')
            ->visible(fn (JournalEntry $r) => $r->status === JournalStatus::Pending && auth()->user()->can('approve', $r))
            ->schema([Textarea::make('reason')->required()])
            ->action(fn (JournalEntry $record, array $data, Action $action) => DomainAction::run(fn () => app(DecideJournal::class)->reject($record, auth()->user(), $data['reason']), $action, 'Entry rejected'));
    }

    public static function reverse(): Action
    {
        return Action::make('reverse')->label('Reverse')->icon('heroicon-o-arrow-uturn-left')->color('danger')
            ->visible(fn (JournalEntry $r) => $r->status === JournalStatus::Posted && $r->reverses_id === null && auth()->user()->can('create', JournalEntry::class))
            ->modalDescription('Posts a new entry that swaps every debit and credit. The original stays on record.')
            ->schema([Textarea::make('reason')->required()])
            ->action(fn (JournalEntry $record, array $data, Action $action) => DomainAction::run(fn () => app(ReverseJournal::class)($record, $data['reason']), $action, 'Entry reversed'));
    }

    public static function sync(): Action
    {
        return Action::make('sync')->label('Post sales, receipts and purchases')->icon('heroicon-o-arrow-path')
            ->modalDescription('Carries invoices, customer receipts, supplier invoices and supplier payments that are not yet in the books into the journal.')
            ->visible(fn () => auth()->user()->can('create', JournalEntry::class))->requiresConfirmation()
            ->action(function () {
                $r = app(SyncOperationalPostings::class)();
                Notification::make()->title("{$r['posted']} posted, {$r['reversed']} reversed")->body($r['skipped'] ? implode("\n", array_slice($r['skipped'], 0, 5)) : null)->color($r['skipped'] ? 'warning' : 'success')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'create' => CreateJournalEntry::route('/create'),
            'view' => ViewJournalEntry::route('/{record}'),
        ];
    }
}
