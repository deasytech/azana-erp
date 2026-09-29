<?php

namespace App\Domain\Farm\Concerns;

use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\Room;
use App\Domain\System\Exceptions\DomainException;

/**
 * Friendly, domain-level version of the composite foreign keys that keep
 * pens/locations inside a consistent building/room/unit chain.
 */
trait ValidatesLocationHierarchy
{
    protected static function bootValidatesLocationHierarchy(): void
    {
        static::saving(function ($model) {
            $room = $model->room_id ? Room::find($model->room_id) : null;

            if ($room && ! $model->building_id) {
                throw new DomainException('A room can only be chosen together with its building.', 'location_hierarchy');
            }

            if ($room && $room->building_id !== (int) $model->building_id) {
                throw new DomainException('The selected room does not belong to the selected building.', 'location_hierarchy');
            }

            if (isset($model->production_unit_id) && $model->building_id) {
                $building = Building::find($model->building_id);

                if ($building && $building->production_unit_id !== (int) $model->production_unit_id) {
                    throw new DomainException('The selected building does not belong to the selected production unit.', 'location_hierarchy');
                }
            }
        });
    }
}
