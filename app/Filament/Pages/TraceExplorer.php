<?php

namespace App\Filament\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Traceability\Actions\TraceProduct;
use App\Filament\Resources\Animals\AnimalResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\FeedProductionOrders\FeedProductionOrderResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Litters\LitterResource;
use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use App\Filament\Resources\Pens\PenResource;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use App\Filament\Resources\SlaughterBatches\SlaughterBatchResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * "Trace this product": enter a meat batch, a pig or a semen batch and see everything that led to it and everything that came
 * of it - from the supplier and the semen batch to the customer. Customers and invoices are shown only to people who may see sales.
 */
class TraceExplorer extends Page
{
    /** What can be traced, and the label of its reference. */
    public const SUBJECTS = ['meat_batch' => 'Meat batch number', 'animal' => 'Animal number', 'semen_batch' => 'Semen batch number'];

    protected string $view = 'filament.pages.trace-explorer';

    protected static ?string $navigationLabel = 'Trace a product';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Slaughter & meat';

    protected static ?int $navigationSort = 2;

    /** The two inputs arrive from the address bar and the page, so both are checked before use. */
    #[Url]
    public string $subject = 'meat_batch';

    #[Url]
    public string $reference = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Animal::class) && $user->can('viewAny', Carcass::class);
    }

    /** @return array{error: ?string, trace: ?array<string, mixed>} */
    public function result(): array
    {
        $reference = strtoupper(trim($this->reference));

        if ($reference === '') {
            return ['error' => null, 'trace' => null];
        }

        if (! array_key_exists($this->subject, self::SUBJECTS)) {
            return ['error' => 'Choose what to trace.', 'trace' => null];
        }

        $tracer = app(TraceProduct::class);
        $found = match ($this->subject) {
            'meat_batch' => ($b = MeatProductionBatch::where('number', $reference)->first()) ? $tracer->forMeatBatch($b) : null,
            'animal' => ($a = Animal::where('animal_number', $reference)->first()) ? $tracer->forAnimal($a) : null,
            'semen_batch' => ($s = SemenBatch::where('number', $reference)->first()) ? $tracer->forSemenBatch($s) : null,
        };

        return $found ? ['error' => null, 'trace' => $this->forThisUser($found)] : ['error' => "Nothing is recorded under {$reference}.", 'trace' => null];
    }

    /**
     * Drops the sales stages (and the links into them) for someone who may not see sales.
     *
     * @param  array<string, mixed>  $trace
     * @return array<string, mixed>
     */
    private function forThisUser(array $trace): array
    {
        if (auth()->user()->can('viewAny', Invoice::class)) {
            return $trace;
        }

        $hidden = collect($trace['stages'])->whereIn('title', ['Sales', 'Customers'])->flatMap(fn ($s) => array_column($s['nodes'], 'key'))->all();

        return [
            'stages' => array_values(array_filter(array_map(fn (array $s) => in_array($s['title'], ['Sales', 'Customers'], true) ? null : $s, $trace['stages']))),
            'edges' => array_values(array_filter($trace['edges'], fn (array $e) => ! in_array($e['from'], $hidden, true) && ! in_array($e['to'], $hidden, true))),
            'upstream' => array_values(array_diff($trace['upstream'], $hidden)),
            'downstream' => array_values(array_diff($trace['downstream'], $hidden)),
        ] + $trace;
    }

    /** Where a node leads: its own page, or null when it has none. */
    public function link(string $type, int $id): ?string
    {
        return match ($type) {
            'animal' => AnimalResource::getUrl('view', ['record' => $id]),
            'meat_production_batch' => MeatProductionBatchResource::getUrl('view', ['record' => $id]),
            'semen_batch' => SemenBatchResource::getUrl('view', ['record' => $id]),
            'production_batch' => ProductionBatchResource::getUrl('view', ['record' => $id]),
            'slaughter_batch' => SlaughterBatchResource::getUrl('view', ['record' => $id]),
            'litter' => LitterResource::getUrl('view', ['record' => $id]),
            'pen' => PenResource::getUrl('edit', ['record' => $id]),
            'supplier' => SupplierResource::getUrl('edit', ['record' => $id]),
            'customer' => CustomerResource::getUrl('view', ['record' => $id]),
            'invoice' => InvoiceResource::getUrl('view', ['record' => $id]),
            'feed_production_batch' => ($order = FeedProductionBatch::find($id)?->feed_production_order_id) ? FeedProductionOrderResource::getUrl('view', ['record' => $order]) : null,
            default => null,
        };
    }

    /** The address of this page tracing the given thing, for "Trace this product" buttons elsewhere. */
    public static function urlFor(string $subject, string $reference): string
    {
        return static::getUrl(['subject' => $subject, 'reference' => $reference]);
    }
}
