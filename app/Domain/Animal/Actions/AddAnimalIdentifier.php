<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalIdentifier;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\IdentifierType;
use Carbon\CarbonInterface;

class AddAnimalIdentifier
{
    public function __invoke(Animal $animal, IdentifierType $type, string $value, ?CarbonInterface $issuedOn = null): AnimalIdentifier
    {
        $animal->refresh(); // never trust a stale status
        $value = strtoupper(trim($value));

        if ($value === '') {
            throw new DomainException('An identifier value is required.', 'identifier_empty');
        }

        if (! $animal->isActive()) {
            throw new DomainException("{$animal->animal_number} is {$animal->status->label()}; identifiers can no longer be added.", 'animal_not_active');
        }

        // Identifiers share one namespace with permanent numbers and are never reused, even once retired.
        if (Animal::where('animal_number', $value)->exists() || AnimalIdentifier::where('type', $type->value)->where('value', $value)->exists()) {
            throw new DomainException("{$type->label()} {$value} is already registered to an animal.", 'identifier_taken');
        }

        return $animal->identifiers()->create(['type' => $type, 'value' => $value, 'issued_on' => $issuedOn]);
    }
}
