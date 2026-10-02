<?php

namespace App\Filament\Resources\StockCounts\RelationManagers;

use App\Domain\Farm\Models\Farm;
use App\Domain\Inventory\Actions\RecordCountLine;
use App\Domain\Inventory\Models\StockCountLine;
use App\Enums\StockCountStatus;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Support\StockForms;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/** What the system expects, what was counted, and the difference, line by line. */
class LinesRelationManager extends RelationManager
{
    use NotifiesDomainErrors;

    protected static string $relationship = 'lines';

    protected static ?string $title = 'Count lines';

    public function isReadOnly(): bool
    {
        return false;
    }

    #[On('stock-count-changed')]
    public function refreshLines(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    private function editable(): bool
    {
        return $this->getOwnerRecord()->status === StockCountStatus::Draft && auth()->user()->can('create', $this->getOwnerRecord()::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.unit', 'batch']))
            ->columns([
                TextColumn::make('item.name')->label('Item'),
                TextColumn::make('batch.batch_number')->label('Batch')->placeholder('-'),
                TextColumn::make('system_quantity')->label('System')->numeric(decimalPlaces: 3)->suffix(fn (StockCountLine $r) => ' '.$r->item->unit->code),
                TextColumn::make('counted_quantity')->label('Counted')->numeric(decimalPlaces: 3)->placeholder('Not counted'),
                TextColumn::make('variance_quantity')->label('Difference')->numeric(decimalPlaces: 3)->placeholder('-')
                    ->color(fn (StockCountLine $r) => match (true) {
                        $r->variance_quantity === null || bccomp((string) $r->variance_quantity, '0', 3) === 0 => null,
                        bccomp((string) $r->variance_quantity, '0', 3) < 0 => 'danger',
                        default => 'success',
                    }),
                TextColumn::make('variance_value_minor')->label('Value')->placeholder('-')
                    ->state(fn (StockCountLine $r) => $r->variance_value_minor === null ? null : Money::ofMinor($r->variance_value_minor, Farm::defaultCurrency())->format()),
                TextColumn::make('reason')->placeholder('-')->limit(40),
            ])
            ->headerActions([
                Action::make('found')->label('Add stock found')->icon('heroicon-o-plus')->visible(fn () => $this->editable())
                    ->schema([
                        StockForms::item(),
                        StockForms::batch(),
                        TextInput::make('counted_quantity')->numeric()->minValue(0)->step(0.001)->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(fn (array $data, Action $action) => $this->record($action, (int) $data['inventory_item_id'], $data['inventory_batch_id'] ?? null, $data)),
            ])
            ->recordActions([
                Action::make('count')->label('Enter count')->icon('heroicon-o-pencil-square')->visible(fn () => $this->editable())
                    ->fillForm(fn (StockCountLine $record) => ['counted_quantity' => $record->counted_quantity, 'reason' => $record->reason])
                    ->schema([
                        TextInput::make('counted_quantity')->numeric()->minValue(0)->step(0.001)->required(),
                        Textarea::make('reason')->helperText('Needed when the count differs from the system.'),
                    ])
                    ->action(fn (StockCountLine $record, array $data, Action $action) => $this->record($action, $record->inventory_item_id, $record->inventory_batch_id, $data)),
            ])
            ->paginated([25, 50]);
    }

    /** @param array<string, mixed> $data */
    private function record(Action $action, int $itemId, ?int $batchId, array $data): void
    {
        $this->attempt(fn () => app(RecordCountLine::class)($this->getOwnerRecord(), $itemId, $batchId, (string) $data['counted_quantity'], $data['reason'] ?? null), $action);
        Notification::make()->title('Count saved')->success()->send();
    }
}
