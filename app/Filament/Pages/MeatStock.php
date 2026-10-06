<?php

namespace App\Filament\Pages;

use App\Domain\Meat\Actions\GetMeatStock;
use App\Domain\Slaughter\Models\Carcass;
use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use App\Filament\Support\MoneyColumn;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Meat in the cold rooms by product, batch and use-by date, with what it cost. */
class MeatStock extends Page
{
    protected string $view = 'filament.pages.meat-stock';

    protected static ?string $navigationLabel = 'Meat stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Carcass::class) ?? false;
    }

    public function getRowsProperty(): Collection
    {
        return app(GetMeatStock::class)();
    }

    public function money(int $minor): string
    {
        return MoneyColumn::format($minor);
    }

    public function batchUrl(int $id): string
    {
        return MeatProductionBatchResource::getUrl('view', ['record' => $id]);
    }
}
