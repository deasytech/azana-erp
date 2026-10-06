<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case FeedIngredient = 'feed_ingredient';
    case FinishedFeed = 'finished_feed';
    case Medicine = 'medicine';
    case Vaccine = 'vaccine';
    case Consumable = 'consumable';
    case SparePart = 'spare_part';
    case Packaging = 'packaging';
    case Semen = 'semen';
    case Meat = 'meat';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
