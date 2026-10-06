<?php

namespace App\Domain\Meat\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Meat\Events\MeatProduced;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CarcassStatus;
use App\Enums\InventoryTransactionType;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns carcasses into meat products in a cold room, all or nothing. What came off the carcasses (and any waste) cannot
 * weigh more than the carcasses did. The whole cost of raising the pigs plus the processing costs is shared out over the
 * products by weight, and each product enters inventory as a stock transaction in its own batch with a use-by date.
 *
 * @param  list<int>  $carcassIds
 * @param  list<array{meat_product_id: int, weight_kg: string}>  $lines
 */
class ProduceMeat
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly ReceiveStock $receiveStock, private readonly ResolveSettings $settings) {}

    /**
     * @param  list<int>  $carcassIds
     * @param  list<array{meat_product_id: int, weight_kg: string}>  $lines
     */
    public function __invoke(array $carcassIds, InventoryLocation|int $location, CarbonInterface $producedOn, array $lines, string $wasteKg = '0', int $otherCostMinor = 0, ?string $notes = null, ?User $actor = null): MeatProductionBatch
    {
        return DB::transaction(function () use ($carcassIds, $location, $producedOn, $lines, $wasteKg, $otherCostMinor, $notes, $actor) {
            $carcasses = Carcass::lockForUpdate()->with('record')->whereIn('id', array_unique($carcassIds))->orderBy('id')->get();
            $location = InventoryLocation::findOrFail($location instanceof InventoryLocation ? $location->id : $location);

            $input = $this->input($carcasses, count(array_unique($carcassIds)), $producedOn);
            $products = $this->products($lines, $input, $wasteKg, $otherCostMinor, $location);

            $liveCost = (int) $carcasses->sum(fn (Carcass $c) => $c->record->live_cost_minor);
            $outputKg = array_reduce($lines, fn (string $sum, array $l) => bcadd($sum, $l['weight_kg'], 2), '0');
            $group = (string) Str::uuid();

            $batch = MeatProductionBatch::create([
                'number' => $this->number($producedOn),
                'produced_on' => $producedOn,
                'inventory_location_id' => $location->id,
                'input_kg' => $input,
                'output_kg' => $outputKg,
                'waste_kg' => $wasteKg,
                'live_cost_minor' => $liveCost,
                'other_cost_minor' => $otherCostMinor,
                'total_cost_minor' => $liveCost + $otherCostMinor,
                'group_uuid' => $group,
                'notes' => $notes,
                'produced_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $this->stock($batch, $products, $lines, $outputKg, $producedOn, $group, $actor);
            Carcass::whereIn('id', $carcasses->pluck('id'))->update(['status' => CarcassStatus::Processed, 'meat_production_batch_id' => $batch->id]);
            MeatProduced::dispatch($batch);

            return $batch->load('lines');
        });
    }

    /** @return string kilograms that can be made into meat from these carcasses */
    private function input($carcasses, int $asked, CarbonInterface $on): string
    {
        if ($carcasses->count() !== $asked || $asked === 0) {
            throw new DomainException('Choose at least one carcass.', 'carcasses_required');
        }

        foreach ($carcasses as $carcass) {
            $carcass->status === CarcassStatus::Hanging || throw new DomainException("{$carcass->number} is {$carcass->status->label()}: only a carcass hanging in the chiller can be processed.", 'carcass_state');

            if ($on->copy()->startOfDay()->lt($carcass->slaughtered_at->copy()->startOfDay())) {
                throw new DomainException("{$carcass->number} was slaughtered on {$carcass->slaughtered_at->format('d M Y')}: meat cannot be made before then.", 'production_date');
            }
        }

        return $carcasses->reduce(fn (string $sum, Carcass $c) => bcadd($sum, $c->usableKg(), 2), '0');
    }

    /**
     * @param  list<array{meat_product_id: int, weight_kg: string}>  $lines
     * @return Collection<int, MeatProduct>
     */
    private function products(array $lines, string $input, string $waste, int $other, InventoryLocation $location)
    {
        $location->is_active || throw new DomainException('Choose an active cold room.', 'inactive_stock_target');
        $lines !== [] || throw new DomainException('Say what the carcasses were made into.', 'lines_required');

        if ($other < 0 || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $waste)) {
            throw new DomainException('Waste must be a weight in kg and other costs cannot be negative.', 'waste');
        }

        $products = MeatProduct::with('item.unit')->whereIn('id', array_column($lines, 'meat_product_id'))->get()->keyBy('id');
        $seen = [];
        $total = $waste;

        foreach ($lines as $line) {
            $product = $products->get($line['meat_product_id'] ?? 0) ?? throw new DomainException('Choose a product from the catalogue for every line.', 'invalid_product');

            ! isset($seen[$product->id]) || throw new DomainException("{$product->name} appears twice; combine the weights.", 'duplicate_product');
            $seen[$product->id] = true;

            $product->is_active || throw new DomainException("{$product->name} is not an active product.", 'inactive_product');
            ($product->item->unit?->code === 'KG' && $product->item->tracks_batches) || throw new DomainException("{$product->name} must be stocked in kg and tracked by batch.", 'product_item');

            if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) ($line['weight_kg'] ?? '')) || bccomp($line['weight_kg'], '0', 2) <= 0) {
                throw new DomainException('Each product weight must be a positive number of kg, with at most 2 decimals.', 'line_weight');
            }

            $total = bcadd($total, $line['weight_kg'], 2);
        }

        if (bccomp($total, $input, 2) > 0) {
            throw new DomainException("The products and waste weigh {$total} kg but the carcasses only provide {$input} kg.", 'mass_balance');
        }

        return $products;
    }

    private function number(CarbonInterface $on): string
    {
        $prefix = (string) $this->settings->get('animals.number_prefix');
        $day = $on->format('Ymd');

        return sprintf('%s-MT-%s-%03d', $prefix, $day, ($this->nextNumber)("meat:{$prefix}:{$day}"));
    }

    /**
     * @param  Collection<int, MeatProduct>  $products
     * @param  list<array{meat_product_id: int, weight_kg: string}>  $lines
     */
    private function stock(MeatProductionBatch $batch, $products, array $lines, string $outputKg, CarbonInterface $on, string $group, ?User $actor): void
    {
        $left = $batch->total_cost_minor;

        foreach ($lines as $i => $line) {
            $product = $products[$line['meat_product_id']];
            $last = $i === array_key_last($lines);
            $cost = $last ? $left : Ratio::toWhole(bcdiv(bcmul((string) $batch->total_cost_minor, $line['weight_kg'], 6), $outputKg, 6));
            $cost = min($cost, $left);
            $left -= $cost;
            $useBy = $on->copy()->startOfDay()->addDays($product->shelf_life_days);

            $received = ($this->receiveStock)(InventoryTransactionType::Production, $product->item, $batch->inventory_location_id, $line['weight_kg'], $on, [
                'value_minor' => $cost, 'batch_number' => $batch->number, 'expiry_date' => $useBy,
                'source_type' => 'meat_production', 'source_id' => $batch->id, 'group_uuid' => $group, 'reason' => "Produced on {$batch->number}",
            ], $actor);

            $batch->lines()->create([
                'meat_product_id' => $product->id, 'weight_kg' => $line['weight_kg'], 'cost_minor' => $cost, 'use_by' => $useBy,
                'inventory_batch_id' => $received->inventory_batch_id, 'inventory_transaction_id' => $received->id,
            ]);
        }
    }
}
