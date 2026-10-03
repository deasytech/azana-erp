<?php

namespace App\Filament\Resources\InventoryTransactions;

use App\Domain\Farm\Models\Farm;
use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Enums\InventoryTransactionType;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Support\DomainAction;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** The stock ledger: every movement ever made. It can be filtered and reversed, never edited or deleted. */
class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static ?string $navigationLabel = 'Stock ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    /** Lines posted by these documents are undone by voiding the document (a goods receipt, a feed record), not here. */
    private const DOCUMENT_SOURCES = ['goods_receipt', 'production_batch', 'animal'];

    /** Reverses a line together with everything posted with it, so a transfer is never left half undone. */
    private static function reverse(InventoryTransaction $record, string $reason): void
    {
        $reverse = app(ReverseInventoryTransaction::class);

        $record->group_uuid ? $reverse->group($record->group_uuid, $reason) : $reverse($record, $reason);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.unit', 'location', 'batch', 'user', 'reverses', 'reversal']))
            ->columns([
                TextColumn::make('occurred_on')->label('Date')->date()->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('item.name')->label('Item')->searchable(),
                TextColumn::make('location.name')->label('Store'),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-'),
                TextColumn::make('quantity')->label('Quantity')->numeric(decimalPlaces: 3)->color(fn (InventoryTransaction $r) => $r->isInbound() ? 'success' : 'danger')
                    ->suffix(fn (InventoryTransaction $r) => ' '.$r->item->unit->code),
                TextColumn::make('value_minor')->label('Value')->state(fn (InventoryTransaction $r) => Money::ofMinor($r->value_minor, Farm::defaultCurrency())->format()),
                TextColumn::make('reason')->limit(40)->placeholder('-')->description(fn (InventoryTransaction $r) => $r->reverses ? 'Reverses #'.$r->reverses->id : ($r->reversal ? 'Reversed by #'.$r->reversal->id : null)),
                TextColumn::make('user.name')->label('By')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('type')->options(AnimalResource::enumOptions(InventoryTransactionType::cases())),
                SelectFilter::make('inventory_item_id')->label('Item')->relationship('item', 'name')->searchable()->preload(),
                SelectFilter::make('inventory_location_id')->label('Store')->relationship('location', 'name'),
                Filter::make('period')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('occurred_on', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('occurred_on', '<=', $d))),
            ])
            ->recordActions([
                Action::make('reverse')->label('Reverse')->color('danger')->requiresConfirmation()
                    ->modalDescription(fn (InventoryTransaction $r) => $r->group_uuid && InventoryTransaction::where('group_uuid', $r->group_uuid)->count() > 1
                        ? 'Adds opposite lines for every line posted together with this one (for example both sides of a transfer). The originals stay on record.'
                        : 'Adds an opposite line to the ledger. The original stays on record.')
                    ->visible(fn (InventoryTransaction $r) => $r->reverses_id === null && $r->reversal === null && ! in_array($r->source_type, self::DOCUMENT_SOURCES, true) && auth()->user()->can('update', $r))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (InventoryTransaction $record, array $data, Action $action) => DomainAction::run(
                        fn () => static::reverse($record, $data['reason']), $action, 'Transaction reversed')),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryTransactions::route('/')];
    }
}
