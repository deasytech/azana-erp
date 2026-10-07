<?php

namespace App\Domain\Mobile\Actions;

use App\Domain\Animal\Actions\LookupAnimal;
use App\Domain\Farm\Models\Pen;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Litter\Models\Litter;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

/**
 * Says what a scanned QR code or barcode is: an animal (tag, number, public id or its QR link), a pen, a production batch, a stock item,
 * a litter or a task - and only if the user may view that kind of thing. Returns null when nothing matches or the user may not see it.
 */
class ResolveScan
{
    public function __construct(private readonly LookupAnimal $animals) {}

    /** @return ?array{type: string, id: int, code: string, label: string} */
    public function __invoke(string $code, User $user): ?array
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return $this->animals($code, $user) ?? $this->simple($code, $user);
    }

    /** @return ?array{type: string, id: int, code: string, label: string} */
    private function animals(string $code, User $user): ?array
    {
        $animal = $user->can('animals.view') ? ($this->animals)($code) : null;

        return $animal ? ['type' => 'animal', 'id' => $animal->id, 'code' => $animal->animal_number, 'label' => "{$animal->animal_number} ({$animal->status->value})"] : null;
    }

    /** @return ?array{type: string, id: int, code: string, label: string} */
    private function simple(string $code, User $user): ?array
    {
        $kinds = [
            ['pen', 'farm-structure.view', fn () => Pen::firstWhere('code', $code), 'code', 'code'],
            ['production_batch', 'production.view', fn () => ProductionBatch::firstWhere('code', $code), 'code', 'name'],
            ['item', 'inventory.view', fn () => InventoryItem::firstWhere('code', $code), 'code', 'name'],
            ['litter', 'breeding.view', fn () => Litter::firstWhere('litter_number', $code), 'litter_number', 'litter_number'],
            ['task', 'tasks.view', fn () => Task::firstWhere('number', $code), 'number', 'title'],
        ];

        foreach ($kinds as [$type, $permission, $find, $codeField, $labelField]) {
            if ($user->can($permission) && ($record = $find())) {
                return ['type' => $type, 'id' => $record->id, 'code' => (string) $record->{$codeField}, 'label' => (string) $record->{$labelField}];
            }
        }

        return null;
    }
}
