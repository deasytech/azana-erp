<?php

namespace App\Filament\Pages;

use App\Domain\Semen\Actions\GetSemenStock;
use App\Domain\Semen\Models\SemenBatch;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Doses in stock by breed, boar, batch and expiry, and whether each batch can be sold. */
class SemenStock extends Page
{
    protected string $view = 'filament.pages.semen-stock';

    protected static ?string $navigationLabel = 'Semen stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static string|UnitEnum|null $navigationGroup = 'Semen';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SemenBatch::class) ?? false;
    }

    public function getRowsProperty(): Collection
    {
        return app(GetSemenStock::class)();
    }

    public function batchUrl(SemenBatch $batch): string
    {
        return SemenBatchResource::getUrl('view', ['record' => $batch]);
    }
}
