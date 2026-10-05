<?php

namespace App\Filament\Resources\FeedFormulas\RelationManagers;

use App\Domain\Feed\Actions\GetFormulaCost;
use App\Domain\Feed\Models\FeedFormulaItem;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Ingredients';

    /** @var array<string, array<string, mixed>>|null cost lines keyed by item name */
    protected ?array $costLines = null;

    private function costLine(FeedFormulaItem $item): array
    {
        $this->costLines ??= collect(app(GetFormulaCost::class)($this->getOwnerRecord())['lines'])->keyBy('item')->all();

        return $this->costLines[$item->item->name] ?? [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('item.unit')->orderByDesc('inclusion_percent'))
            ->columns([
                TextColumn::make('item.name')->label('Ingredient'),
                TextColumn::make('inclusion_percent')->label('Inclusion')->suffix('%'),
                TextColumn::make('kg_per_tonne')->label('kg per tonne of feed')->state(fn (FeedFormulaItem $r) => $this->costLine($r)['kg_per_tonne'] ?? '-'),
                MoneyColumn::computed('unit_cost', 'Cost per unit', fn (FeedFormulaItem $r) => $this->costLine($r)['unit_cost_minor'] ?? null),
                TextColumn::make('notes')->placeholder('-'),
            ])
            ->paginated(false);
    }
}
