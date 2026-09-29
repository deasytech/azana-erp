<?php

namespace App\Enums;

/** Categories of admin-editable value lists stored in lookup_values. */
enum LookupCategory: string
{
    case ProductionUnitType = 'production_unit_type';
    case BuildingType = 'building_type';
    case LocationType = 'location_type';
    case PenPurpose = 'pen_purpose';
    case PriceCategory = 'price_category';
    case AnimalCategory = 'animal_category';
    case MovementReason = 'movement_reason';

    public function label(): string
    {
        return match ($this) {
            self::ProductionUnitType => 'Production unit type',
            self::BuildingType => 'Building type',
            self::LocationType => 'Location type',
            self::PenPurpose => 'Pen purpose',
            self::PriceCategory => 'Price category',
            self::AnimalCategory => 'Animal category',
            self::MovementReason => 'Movement reason',
        };
    }
}
