<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\Farm;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Semen\Actions\GetSemenPrice;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\SalesLineKind;
use App\Models\User;
use App\Support\Ratio;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Drafts an order for a customer. Each line sells one of:
 *  - semen:      semen_batch_id, inventory_location_id, quantity (doses); the price defaults to the price list's
 *  - pig_animal: animal_id, unit (head|kg), quantity (kg when priced by weight), unit_price_minor
 *  - pig_batch:  production_batch_id, heads, unit (head|kg), quantity (total kg when priced by weight), unit_price_minor
 * optionally with discount_percent. The price is copied onto the line, so later price changes never alter the order.
 */
class CreateSalesOrder
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly GetSemenPrice $semenPrice) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(Customer|int $customer, array $lines, CarbonInterface $orderedOn, ?string $notes = null, ?User $actor = null, ?string $idempotencyKey = null): SalesOrder
    {
        if ($lines === []) {
            throw new DomainException('Add at least one line.', 'lines_required');
        }

        if ($orderedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('An order cannot be dated in the future.', 'order_future');
        }

        return DB::transaction(function () use ($customer, $lines, $orderedOn, $notes, $actor, $idempotencyKey) {
            if ($idempotencyKey && ($existing = SalesOrder::firstWhere('idempotency_key', $idempotencyKey))) {
                return $existing->customer_id === (int) ($customer instanceof Customer ? $customer->id : $customer) ? $existing : throw new DomainException('This idempotency key was already used for another customer.', 'idempotency_conflict');
            }

            $customer = Customer::findOrFail($customer instanceof Customer ? $customer->id : $customer);
            $customer->is_active || throw new DomainException("{$customer->name} is not an active customer.", 'inactive_customer');

            $rows = array_map(fn (array $line) => $this->line($line), array_values($lines));
            $this->assertNoDuplicates($rows);

            $order = SalesOrder::create([
                'number' => sprintf('SO-%06d', ($this->nextNumber)('sales_order')),
                'customer_id' => $customer->id,
                'ordered_on' => $orderedOn,
                'currency_code' => Farm::defaultCurrency(),
                'total_minor' => array_sum(array_column($rows, 'line_total_minor')),
                'notes' => $notes,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($rows as $row) {
                $order->lines()->create($row);
            }

            return $order->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function line(array $line): array
    {
        $kind = SalesLineKind::tryFrom((string) ($line['kind'] ?? '')) ?? throw new DomainException('Choose what each line sells.', 'line_kind');

        $row = match ($kind) {
            SalesLineKind::Semen => $this->semenLine($line),
            SalesLineKind::PigAnimal => $this->animalLine($line),
            SalesLineKind::PigBatch => $this->batchLine($line),
        };

        $discount = (string) ($line['discount_percent'] ?? '0');

        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $discount) || bccomp($discount, '100', 2) > 0) {
            throw new DomainException('A discount is a percentage from 0 to 100.', 'discount');
        }

        if (! is_int($row['unit_price_minor']) || $row['unit_price_minor'] < 0) {
            throw new DomainException('Give a price (zero or more, in whole minor units) for every line.', 'line_price');
        }

        $gross = bcmul((string) $row['quantity'], (string) $row['unit_price_minor'], 6);

        return $row + [
            'kind' => $kind,
            'discount_percent' => $discount,
            'line_total_minor' => Ratio::toWhole(bcmul($gross, bcsub('1', bcdiv($discount, '100', 6), 6), 6)),
        ];
    }

    /** @param array<string, mixed> $line */
    private function semenLine(array $line): array
    {
        $batch = SemenBatch::with('breed')->find($line['semen_batch_id'] ?? 0) ?? throw new DomainException('Choose a semen batch.', 'semen_batch');
        $location = InventoryLocation::where('is_active', true)->find($line['inventory_location_id'] ?? 0) ?? throw new DomainException('Choose the store the doses come from.', 'semen_store');
        $doses = $line['quantity'] ?? null;

        if (! is_numeric($doses) || (string) (int) $doses !== (string) $doses || (int) $doses < 1) {
            throw new DomainException('Semen is sold in whole doses.', 'semen_doses');
        }

        $price = $line['unit_price_minor'] ?? $this->defaultPrice($batch);

        return [
            'description' => "Semen {$batch->number}".($batch->breed ? " ({$batch->breed->name})" : ''),
            'unit' => 'dose', 'quantity' => (string) (int) $doses, 'unit_price_minor' => $price,
            'semen_batch_id' => $batch->id, 'inventory_location_id' => $location->id,
        ];
    }

    private function defaultPrice(SemenBatch $batch): ?int
    {
        $item = $batch->breed_id ? InventoryItem::where('breed_id', $batch->breed_id)->first() : null;

        return $item ? ($this->semenPrice)($item)['price_minor'] ?? null : null;
    }

    /** @param array<string, mixed> $line */
    private function animalLine(array $line): array
    {
        $animal = Animal::find($line['animal_id'] ?? 0) ?? throw new DomainException('Choose the pig being sold.', 'animal');
        $unit = $this->unit($line);

        return [
            'description' => "Pig {$animal->animal_number}", 'unit' => $unit, 'heads' => 1,
            'quantity' => $unit === 'head' ? '1' : $this->kilograms($line), 'unit_price_minor' => $line['unit_price_minor'] ?? null,
            'animal_id' => $animal->id,
        ];
    }

    /** @param array<string, mixed> $line */
    private function batchLine(array $line): array
    {
        $batch = ProductionBatch::find($line['production_batch_id'] ?? 0) ?? throw new DomainException('Choose the batch the pigs come from.', 'production_batch');
        $heads = $line['heads'] ?? null;
        $unit = $this->unit($line);

        if (! is_numeric($heads) || (string) (int) $heads !== (string) $heads || (int) $heads < 1) {
            throw new DomainException('Say how many pigs are being sold.', 'heads');
        }

        return [
            'description' => "{$heads} pigs from {$batch->code}", 'unit' => $unit, 'heads' => (int) $heads,
            'quantity' => $unit === 'head' ? (string) (int) $heads : $this->kilograms($line), 'unit_price_minor' => $line['unit_price_minor'] ?? null,
            'production_batch_id' => $batch->id,
        ];
    }

    /** @param array<string, mixed> $line */
    private function unit(array $line): string
    {
        return in_array($line['unit'] ?? 'head', ['head', 'kg'], true) ? $line['unit'] ?? 'head' : throw new DomainException('Pigs are priced per head or per kg.', 'line_unit');
    }

    /** @param array<string, mixed> $line */
    private function kilograms(array $line): string
    {
        $kg = (string) ($line['quantity'] ?? '');

        return preg_match('/^\d{1,8}(\.\d{1,3})?$/', $kg) && bccomp($kg, '0', 3) > 0 ? $kg : throw new DomainException('Enter the live weight sold, in kg.', 'line_weight');
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertNoDuplicates(array $rows): void
    {
        foreach (['semen_batch_id', 'animal_id', 'production_batch_id'] as $key) {
            $ids = array_filter(array_column($rows, $key));

            if (count($ids) !== count(array_unique($ids))) {
                throw new DomainException('The same batch or pig appears on two lines; combine them.', 'duplicate_line');
            }
        }
    }
}
