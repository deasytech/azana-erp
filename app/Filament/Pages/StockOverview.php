<?php

namespace App\Filament\Pages;

use App\Domain\Farm\Models\Farm;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Enums\InventoryTransactionType as T;
use App\Filament\Concerns\NotifiesDomainErrors;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\StockForms;
use App\Filament\Widgets\StockByStoreChartWidget;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Stock on hand by item, store and batch, with its value; also where stock is received, used and moved. */
class StockOverview extends Page
{
    use NotifiesDomainErrors;

    protected string $view = 'filament.pages.stock-overview';

    protected static ?string $navigationLabel = 'Stock on hand';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    /** Filters. Public so the page can bind them, which means a client can send anything: they are validated before use. */
    public ?string $itemId = null;

    public ?string $locationId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', InventoryTransaction::class) ?? false;
    }

    /** @return Collection<int, InventoryLayer> */
    public function getLevelsProperty(): Collection
    {
        return app(GetStockLevels::class)($this->validId($this->itemId), $this->validId($this->locationId));
    }

    public function getTotalValueProperty(): string
    {
        return $this->money($this->levels->sum('value_minor'));
    }

    public function money(int $minor): string
    {
        return Money::ofMinor($minor, Farm::defaultCurrency())->format();
    }

    /** @return array<int, string> */
    public function itemOptions(): array
    {
        return InventoryItem::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function storeOptions(): array
    {
        return InventoryLocation::orderBy('name')->pluck('name', 'id')->all();
    }

    private function validId(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /** The value of stock drawn by store, filtered with the screen above it. */
    protected function getFooterWidgets(): array
    {
        return [StockByStoreChartWidget::class];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return ['item' => $this->validId($this->itemId), 'location' => $this->validId($this->locationId)];
    }

    /** Tell the chart when the item or store filter changes while it is open. */
    public function updated(string $property): void
    {
        if (in_array($property, ['itemId', 'locationId'], true)) {
            $this->dispatch('stock-overview-filter-changed', item: $this->validId($this->itemId), location: $this->validId($this->locationId));
        }
    }

    protected function getHeaderActions(): array
    {
        return [$this->receiveAction(), $this->issueAction(), $this->transferAction()];
    }

    private function canPost(): bool
    {
        return auth()->user()->can('create', InventoryTransaction::class);
    }

    private function receiveAction(): Action
    {
        return Action::make('receive')->label('Receive stock')->icon('heroicon-o-arrow-down-tray')
            ->visible(fn () => $this->canPost())
            ->schema([
                Select::make('type')->options([T::Opening->value => 'Opening balance', T::Purchase->value => 'Purchase (no order)', T::Return->value => 'Return'])->default(T::Opening->value)->required(),
                StockForms::item(),
                StockForms::store(),
                TextInput::make('quantity')->numeric()->minValue(0.001)->step(0.001)->required(),
                MoneyInput::make('unit_cost_minor', 'Cost per unit')->required(),
                DatePicker::make('occurred_on')->label('Date')->default(now())->maxDate(now())->required(),
                TextInput::make('batch_number')->visible(fn ($get) => StockForms::tracksBatches($get('inventory_item_id')))->required(fn ($get) => StockForms::tracksBatches($get('inventory_item_id'))),
                DatePicker::make('expiry_date')->visible(fn ($get) => StockForms::tracksExpiry($get('inventory_item_id')))->required(fn ($get) => StockForms::tracksExpiry($get('inventory_item_id'))),
                Textarea::make('reason'),
            ])
            ->action(function (array $data, Action $action) {
                $this->attempt(fn () => app(ReceiveStock::class)(T::from($data['type']), (int) $data['inventory_item_id'], (int) $data['inventory_location_id'], (string) $data['quantity'], Carbon::parse($data['occurred_on']), [
                    'unit_cost_minor' => $data['unit_cost_minor'], 'batch_number' => $data['batch_number'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null, 'reason' => $data['reason'] ?? null,
                ]), $action);

                Notification::make()->title('Stock received')->success()->send();
            });
    }

    private function issueAction(): Action
    {
        return Action::make('issue')->label('Use / write off')->icon('heroicon-o-arrow-up-tray')->color('warning')
            ->visible(fn () => $this->canPost())
            ->schema([
                Select::make('type')->options([T::Consumption->value => 'Used on the farm', T::Wastage->value => 'Wasted / spoiled'])->default(T::Consumption->value)->required()->live(),
                StockForms::item(),
                StockForms::store(),
                StockForms::batch()->helperText('Leave empty to use the batch closest to expiry first.'),
                TextInput::make('quantity')->numeric()->minValue(0.001)->step(0.001)->required(),
                DatePicker::make('occurred_on')->label('Date')->default(now())->maxDate(now())->required(),
                Textarea::make('reason')->required(fn ($get) => $get('type') === T::Wastage->value),
            ])
            ->action(function (array $data, Action $action) {
                $batchId = $data['inventory_batch_id'] ?? null;

                $this->attempt(fn () => app(IssueStock::class)(T::from($data['type']), (int) $data['inventory_item_id'], (int) $data['inventory_location_id'], (string) $data['quantity'], Carbon::parse($data['occurred_on']), [
                    'batch' => $batchId === null || $batchId === '' ? null : (int) $batchId, 'reason' => $data['reason'] ?? null,
                ]), $action);

                Notification::make()->title('Stock taken out')->success()->send();
            });
    }

    private function transferAction(): Action
    {
        return Action::make('transfer')->label('Transfer')->icon('heroicon-o-arrows-right-left')
            ->visible(fn () => $this->canPost())
            ->schema([
                StockForms::item(),
                StockForms::store('from_id', 'From store'),
                StockForms::store('to_id', 'To store'),
                StockForms::batch(),
                TextInput::make('quantity')->numeric()->minValue(0.001)->step(0.001)->required(),
                DatePicker::make('occurred_on')->label('Date')->default(now())->maxDate(now())->required(),
                Textarea::make('reason'),
            ])
            ->action(function (array $data, Action $action) {
                $batchId = $data['inventory_batch_id'] ?? null;

                $this->attempt(fn () => app(TransferStock::class)((int) $data['inventory_item_id'], (int) $data['from_id'], (int) $data['to_id'], (string) $data['quantity'], Carbon::parse($data['occurred_on']), [
                    'batch' => $batchId === null || $batchId === '' ? null : (int) $batchId, 'reason' => $data['reason'] ?? null,
                ]), $action);

                Notification::make()->title('Stock transferred')->success()->send();
            });
    }
}
