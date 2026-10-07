<?php

namespace App\Http\Resources;

use App\Domain\Animal\Models\Animal;
use App\Filament\Resources\Animals\AnimalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Animal */
class AnimalSummary extends JsonResource
{
    /** Scanners and apps read the animal's fields directly, not under a "data" key. */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['category', 'breed', 'currentPen.building', 'currentLocation']);

        return [
            'animal_number' => $this->animal_number,
            'public_id' => $this->public_id,
            'sex' => $this->sex->value,
            'category' => $this->category->name,
            'breed' => $this->breed?->name,
            'status' => $this->status->value,
            'position' => $this->positionLabel(),
            'latest_weight_kg' => $this->latestWeight()?->weight_kg,
            'url' => AnimalResource::getUrl('view', ['record' => $this->resource]),
        ];
    }
}
