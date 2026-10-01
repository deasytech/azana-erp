<?php

namespace App\Filament\Pages;

use App\Domain\Production\Actions\GetProductionSummary;
use App\Domain\Production\Models\ProductionBatch;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Key figures for every active batch side by side. */
class ProductionOverview extends Page
{
    protected string $view = 'filament.pages.production-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', ProductionBatch::class) ?? false;
    }

    /** @return Collection<int, array{batch: ProductionBatch, performance: array<string, int|string|null>}> */
    public function getSummaryProperty(): Collection
    {
        return app(GetProductionSummary::class)();
    }

    public function batchUrl(ProductionBatch $batch): string
    {
        return ProductionBatchResource::getUrl('view', ['record' => $batch]);
    }
}
