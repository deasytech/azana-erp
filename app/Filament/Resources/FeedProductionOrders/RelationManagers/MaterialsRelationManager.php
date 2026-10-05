<?php

namespace App\Filament\Resources\FeedProductionOrders\RelationManagers;

use App\Domain\Feed\Actions\ManageFeedProductionOrder;
use App\Domain\Feed\Models\FeedProductionOrderLine;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/** The materials the order needs, what was used, and what the source store holds against the plan. */
class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Materials';

    /** @var array<int, array{on_hand: string, short: string}>|null by line id */
    protected ?array $stock = null;

    #[On('feed-order-changed')]
    public function refreshMaterials(): void
    {
        $this->stock = null;
    }

    private function stock(FeedProductionOrderLine $line): array
    {
        $this->stock ??= app(ManageFeedProductionOrder::class)->availability($this->getOwnerRecord())->mapWithKeys(fn ($row) => [$row['line']->id => $row])->all();

        return $this->stock[$line->id];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('item.unit'))
            ->columns([
                TextColumn::make('item.name')->label('Material'),
                TextColumn::make('planned_quantity')->label('Planned')->numeric(decimalPlaces: 3)->suffix(fn (FeedProductionOrderLine $r) => ' '.$r->item->unit->code),
                TextColumn::make('actual_quantity')->label('Used')->numeric(decimalPlaces: 3)->placeholder('Not confirmed'),
                TextColumn::make('difference')->label('Difference')->state(fn (FeedProductionOrderLine $r) => $r->isConfirmed() ? bcsub((string) $r->actual_quantity, (string) $r->planned_quantity, 3) : '-'),
                TextColumn::make('on_hand')->label('In store now')->state(fn (FeedProductionOrderLine $r) => $this->stock($r)['on_hand']),
                TextColumn::make('short')->label('Short by')->state(fn (FeedProductionOrderLine $r) => $this->stock($r)['short'])
                    ->color(fn (FeedProductionOrderLine $r) => bccomp($this->stock($r)['short'], '0', 3) > 0 ? 'danger' : null),
            ])
            ->paginated(false);
    }
}
