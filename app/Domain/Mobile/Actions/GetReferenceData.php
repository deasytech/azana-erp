<?php

namespace App\Domain\Mobile\Actions;

use App\Domain\Farm\Models\Location;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\VaccinationSchedule;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Mobile\Services\QuickCatalogue;
use App\Enums\LookupCategory;
use App\Enums\ServiceMethod;
use App\Models\User;

/**
 * The lists a device keeps offline so it can fill in forms without a connection: pens, stores, feed types, medicines, causes and so on,
 * with a version (a hash of the content). A device that sends the version it holds is told when nothing changed.
 */
class GetReferenceData
{
    public function __construct(private readonly QuickCatalogue $quickActions) {}

    /** @return array{version: string, unchanged?: bool, data?: array<string, mixed>} */
    public function __invoke(User $user, ?string $knownVersion = null): array
    {
        $data = [
            'quick_actions' => $this->quickActions->types(),
            'service_methods' => array_map(fn (ServiceMethod $m) => $m->value, ServiceMethod::cases()),
            'pens' => $this->rows(Pen::where('is_active', true)->orderBy('code'), ['id', 'code']),
            'locations' => $this->rows(Location::where('is_active', true)->orderBy('name'), ['id', 'code', 'name']),
            'stores' => $this->rows(InventoryLocation::where('is_active', true)->orderBy('code'), ['id', 'code', 'name']),
            'feed_types' => $this->rows(FeedType::where('is_active', true)->orderBy('name'), ['id', 'code', 'name']),
            'medicines' => $this->rows(Medicine::where('is_active', true)->orderBy('name'), ['id', 'code', 'name']),
            'vaccination_schedules' => $this->rows(VaccinationSchedule::where('is_active', true)->orderBy('name'), ['id', 'name', 'medicine_id']),
            'items' => $this->rows(InventoryItem::where('is_active', true)->orderBy('name'), ['id', 'code', 'name']),
            'mortality_causes' => $this->lookup(LookupCategory::MortalityCause),
            'movement_reasons' => $this->lookup(LookupCategory::MovementReason),
            'animal_categories' => $this->lookup(LookupCategory::AnimalCategory),
        ];

        $version = substr(sha1((string) json_encode($data)), 0, 16);

        return $knownVersion === $version ? ['version' => $version, 'unchanged' => true] : ['version' => $version, 'data' => $data];
    }

    /** @param list<string> $columns */
    private function rows($query, array $columns): array
    {
        return $query->get($columns)->map(fn ($r) => $r->only($columns))->all();
    }

    /** @return list<array{id: int, code: string, name: string}> */
    private function lookup(LookupCategory $category): array
    {
        return LookupValue::where('category', $category->value)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($v) => ['id' => $v->id, 'code' => $v->code, 'name' => $v->name])->all();
    }
}
