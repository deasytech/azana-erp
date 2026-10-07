<?php

namespace App\Domain\Traceability\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalParentage;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\Piglet;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchAnimal;
use App\Domain\Sales\Models\InvoiceLine;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Domain\Traceability\TraceGraph;
use App\Enums\InventoryTransactionType;
use Illuminate\Support\Collection;

/**
 * "Trace this product": for a meat batch, a pig or a semen batch, everything that led to it and everything that came of it.
 * Upstream: slaughter, the pig, its batch and pens, its feed (the finished feed batch, the raw material batches that went
 * into it and their suppliers) and its litter, sow, boar and semen batch. Downstream: the invoices and customers it was sold to.
 *
 * @return array{subject: array{key: string, type: string, id: int, label: string, detail: ?string}, stages: list<array{title: string, nodes: list<array<string, mixed>>}>, edges: list<array{from: string, to: string, label: ?string}>, upstream: list<string>, downstream: list<string>}
 */
class TraceProduct
{
    /** @return array<string, mixed> */
    public function forMeatBatch(MeatProductionBatch $batch): array
    {
        $graph = new TraceGraph;
        $subject = $this->meatBatch($graph, $batch);

        foreach ($batch->carcasses()->with('record.batch', 'record.animal', 'record.productionBatch')->orderBy('id')->get() as $carcass) {
            $this->carcassOrigin($graph, $carcass, $subject);
        }

        $this->meatSales($graph, $batch, $subject);

        return $this->result($graph, $subject);
    }

    /** @return array<string, mixed> */
    public function forAnimal(Animal $animal): array
    {
        $graph = new TraceGraph;
        $subject = $this->animalOrigin($graph, $animal);

        foreach (SlaughterRecord::with('batch', 'carcass.productionBatch')->where('animal_id', $animal->id)->get() as $record) {
            $day = $graph->node('Slaughter', 'slaughter_batch', $record->batch->id, $record->batch->number, 'Slaughter day, '.$record->batch->scheduled_on->format('d M Y'));
            $graph->edge($subject, $day);

            if ($record->carcass) {
                $carcass = $this->carcass($graph, $record->carcass, $day);

                if ($record->carcass->productionBatch) {
                    $meat = $this->meatBatch($graph, $record->carcass->productionBatch);
                    $graph->edge($carcass, $meat);
                    $this->meatSales($graph, $record->carcass->productionBatch, $meat);
                }
            }
        }

        $this->liveSales($graph, 'animal_id', $animal->id, $subject);

        return $this->result($graph, $subject);
    }

    /** @return array<string, mixed> */
    public function forSemenBatch(SemenBatch $batch): array
    {
        $graph = new TraceGraph;
        $batch->loadMissing('boar', 'breed', 'collection', 'latestQc');
        $boar = $graph->node('Genetics', 'animal', $batch->boar->id, $batch->boar->animal_number, 'Boar'.($batch->breed ? ", {$batch->breed->name}" : ''));
        $subject = $graph->node('Semen', 'semen_batch', $batch->id, $batch->number, "Collected {$batch->collected_on->format('d M Y')}, {$batch->status->label()}");
        $graph->edge($boar, $subject, 'collected from');

        foreach (InvoiceLine::with('invoice.customer')->where('semen_batch_id', $batch->id)->get() as $line) {
            $this->sale($graph, $line, $subject, "{$line->quantity} doses");
        }

        foreach (BreedingService::with('sow')->where('semen_batch_id', $batch->id)->get() as $service) {
            $sow = $graph->node('Pig', 'animal', $service->sow->id, $service->sow->animal_number, "Inseminated {$service->serviced_on->format('d M Y')}");
            $graph->edge($subject, $sow, 'used on');

            foreach (Litter::where('breeding_service_id', $service->id)->get() as $litter) {
                $node = $graph->node('Litter', 'litter', $litter->id, $litter->litter_number, 'Litter from this semen');
                $graph->edge($sow, $node);
            }
        }

        return $this->result($graph, $subject);
    }

    // ---- upstream ----

    private function carcassOrigin(TraceGraph $graph, Carcass $carcass, string $meatKey): void
    {
        $record = $carcass->record;
        $day = $graph->node('Slaughter', 'slaughter_batch', $record->batch->id, $record->batch->number, 'Slaughter day, '.$record->batch->scheduled_on->format('d M Y'));
        $carcassKey = $this->carcass($graph, $carcass, $day);
        $graph->edge($carcassKey, $meatKey);

        if ($record->animal) {
            $graph->edge($this->animalOrigin($graph, $record->animal), $day);
        } elseif ($record->productionBatch) {
            $batch = $graph->node('Pig', 'production_batch', $record->productionBatch->id, $record->productionBatch->code, "{$record->heads} pigs slaughtered from this batch");
            $graph->edge($batch, $day);
            $this->batchFeed($graph, $record->productionBatch, $batch);
        }
    }

    private function carcass(TraceGraph $graph, Carcass $carcass, string $dayKey): string
    {
        $key = $graph->node('Slaughter', 'carcass', $carcass->id, $carcass->number, "{$carcass->hot_weight_kg} kg from {$carcass->live_weight_kg} kg live ({$carcass->dressing_percent}%)");
        $graph->edge($dayKey, $key);

        return $key;
    }

    /** The pig and everything behind it; returns the pig's node key. */
    private function animalOrigin(TraceGraph $graph, Animal $animal): string
    {
        $animal->loadMissing('breed', 'parentage');
        $pig = $graph->node('Pig', 'animal', $animal->id, $animal->animal_number, trim(($animal->breed?->name ?? '').($animal->birth_date ? ', born '.$animal->birth_date->format('d M Y') : ''), ', '));

        $this->parents($graph, $animal, $pig);

        foreach ($animal->movements()->with('toPen')->whereNotNull('to_pen_id')->orderBy('moved_at')->get()->unique('to_pen_id') as $move) {
            $graph->edge($graph->node('Housing', 'pen', $move->to_pen_id, "Pen {$move->toPen->code}", "from {$move->moved_at->format('d M Y')}"), $pig, 'housed in');
        }

        $batches = ProductionBatchAnimal::with('batch')->where('animal_id', $animal->id)->get()->pluck('batch')->unique('id');

        foreach ($batches as $batch) {
            $batchKey = $graph->node('Pig', 'production_batch', $batch->id, $batch->code, $batch->name);
            $graph->edge($batchKey, $pig, 'member of');
            $this->batchFeed($graph, $batch, $batchKey);
        }

        $this->feedRecords($graph, FeedConsumptionRecord::with('feedType')->whereNull('voided_at')->where('animal_id', $animal->id)->get(), $pig);

        return $pig;
    }

    private function parents(TraceGraph $graph, Animal $animal, string $pig): void
    {
        $litter = Piglet::with('litter.sow', 'litter.sire', 'litter.service.semenBatch.boar')->where('animal_id', $animal->id)->first()?->litter;

        if ($litter) {
            $litterKey = $graph->node('Litter', 'litter', $litter->id, $litter->litter_number, 'Born '.$litter->born_on->format('d M Y'));
            $graph->edge($litterKey, $pig, 'born in');
            $graph->edge($graph->node('Genetics', 'animal', $litter->sow->id, $litter->sow->animal_number, 'Sow'), $litterKey, 'dam');

            $semen = $litter->service?->semenBatch;

            if ($semen) {
                $semenKey = $graph->node('Genetics', 'semen_batch', $semen->id, $semen->number, "Semen from {$semen->boar->animal_number}");
                $graph->edge($graph->node('Genetics', 'animal', $semen->boar->id, $semen->boar->animal_number, 'Boar'), $semenKey, 'collected from');
                $graph->edge($semenKey, $litterKey, 'inseminated with');
            } elseif ($litter->sire) {
                $graph->edge($graph->node('Genetics', 'animal', $litter->sire->id, $litter->sire->animal_number, 'Boar'), $litterKey, 'sire');
            }

            return;
        }

        /** @var ?AnimalParentage $parentage */
        $parentage = $animal->parentage;

        foreach (['dam' => 'Sow', 'sire' => 'Boar'] as $role => $label) {
            $parent = $parentage?->{$role};
            $parent && $graph->edge($graph->node('Genetics', 'animal', $parent->id, $parent->animal_number, $label), $pig, $role);
        }
    }

    /** Feed recorded against a whole batch, traced to the feed that was used and what went into it. */
    private function batchFeed(TraceGraph $graph, ProductionBatch $batch, string $batchKey): void
    {
        $this->feedRecords($graph, $batch->feedRecords()->with('feedType')->whereNull('voided_at')->get(), $batchKey);
    }

    /** @param Collection<int, FeedConsumptionRecord> $records */
    private function feedRecords(TraceGraph $graph, Collection $records, string $eatenBy): void
    {
        foreach ($records->groupBy('feed_type_id') as $perType) {
            $type = $perType->first()->feedType;
            $feed = $graph->node('Feed', 'feed_type', $type->id, $type->name, $perType->sum('quantity_kg').' kg fed');
            $graph->edge($feed, $eatenBy, 'fed');

            foreach ($perType->pluck('inventory_group')->filter()->unique() as $group) {
                $this->feedBatchesOf($graph, $group, $feed);
            }
        }
    }

    /** The finished feed batches a feed record drew from, with the raw materials and suppliers behind each. */
    private function feedBatchesOf(TraceGraph $graph, string $group, string $feedKey): void
    {
        $batchIds = InventoryTransaction::where('group_uuid', $group)->whereNotNull('inventory_batch_id')->pluck('inventory_batch_id')->unique();

        foreach (FeedProductionBatch::with('order.formula')->whereIn('inventory_batch_id', $batchIds)->get() as $production) {
            $label = $production->order->number.' (batch '.$production->inventoryBatch?->batch_number.')';
            $batchKey = $graph->node('Feed', 'feed_production_batch', $production->id, $label, "{$production->order->formula->name}, made {$production->produced_on->format('d M Y')}");
            $graph->edge($batchKey, $feedKey, 'drawn from');

            $raw = InventoryTransaction::with('batch.supplier', 'item')->where('group_uuid', $production->group_uuid)->where('type', InventoryTransactionType::Consumption)->whereNull('reverses_id')->get();

            foreach ($raw->filter(fn (InventoryTransaction $t) => $t->inventory_batch_id !== null)->unique('inventory_batch_id') as $line) {
                $this->rawMaterial($graph, $line->batch, $line->item->name, $batchKey);
            }

            foreach ($raw->whereNull('inventory_batch_id')->unique('inventory_item_id') as $line) {
                $graph->edge($graph->node('Raw materials', 'inventory_item', $line->item->id, $line->item->name, 'Used without batch tracking'), $batchKey, 'used in');
            }
        }
    }

    private function rawMaterial(TraceGraph $graph, InventoryBatch $batch, string $item, string $feedBatchKey): void
    {
        $key = $graph->node('Raw materials', 'inventory_batch', $batch->id, "{$item}, batch {$batch->batch_number}", $batch->expiry_date ? 'Expires '.$batch->expiry_date->format('d M Y') : null);
        $graph->edge($key, $feedBatchKey, 'used in');

        if ($batch->supplier) {
            $graph->edge($graph->node('Suppliers', 'supplier', $batch->supplier->id, $batch->supplier->name, $batch->supplier->code), $key, 'supplied');
        }
    }

    // ---- downstream ----

    private function meatBatch(TraceGraph $graph, MeatProductionBatch $batch): string
    {
        return $graph->node('Meat', 'meat_production_batch', $batch->id, $batch->number, "Made {$batch->produced_on->format('d M Y')}, {$batch->output_kg} kg");
    }

    private function meatSales(TraceGraph $graph, MeatProductionBatch $batch, string $meatKey): void
    {
        $lines = InvoiceLine::with('invoice.customer', 'meatLine.product')->whereIn('meat_production_line_id', $batch->lines()->pluck('id'))->get();

        foreach ($lines as $line) {
            $this->sale($graph, $line, $meatKey, "{$line->meatLine->product->name}, {$line->quantity} kg");
        }
    }

    private function liveSales(TraceGraph $graph, string $column, int $id, string $fromKey): void
    {
        foreach (InvoiceLine::with('invoice.customer')->where($column, $id)->get() as $line) {
            $this->sale($graph, $line, $fromKey, 'Sold alive');
        }
    }

    private function sale(TraceGraph $graph, InvoiceLine $line, string $fromKey, string $what): void
    {
        $invoice = $line->invoice;
        $invoiceKey = $graph->node('Sales', 'invoice', $invoice->id, $invoice->number, "{$what}, {$invoice->issued_on->format('d M Y')}");
        $graph->edge($fromKey, $invoiceKey, 'sold on');
        $graph->edge($invoiceKey, $graph->node('Customers', 'customer', $invoice->customer->id, $invoice->customer->name, $invoice->customer->code), 'sold to');
    }

    /** @return array<string, mixed> */
    private function result(TraceGraph $graph, string $subject): array
    {
        $stages = $graph->stages();
        $nodes = collect($stages)->flatMap(fn ($s) => $s['nodes'])->keyBy('key');

        return [
            'subject' => $nodes[$subject],
            'stages' => $stages,
            'edges' => $graph->edges(),
            'upstream' => $graph->ancestors($subject),
            'downstream' => $this->descendants($graph, $subject),
        ];
    }

    /** @return list<string> */
    private function descendants(TraceGraph $graph, string $key): array
    {
        $found = [];
        $queue = [$key];

        while ($queue) {
            $current = array_shift($queue);

            foreach ($graph->edges() as $edge) {
                if ($edge['from'] === $current && ! in_array($edge['to'], $found, true)) {
                    $found[] = $edge['to'];
                    $queue[] = $edge['to'];
                }
            }
        }

        return $found;
    }
}
