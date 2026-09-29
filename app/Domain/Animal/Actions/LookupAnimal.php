<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalIdentifier;

/**
 * Resolves whatever was scanned or typed (permanent number, ear tag, RFID, barcode,
 * QR payload URL or public id) to an animal.
 */
class LookupAnimal
{
    public function __invoke(string $code): ?Animal
    {
        $code = strtoupper(trim(basename(parse_url(trim($code), PHP_URL_PATH) ?: $code)));

        if ($code === '') {
            return null;
        }

        return Animal::where('animal_number', $code)->orWhere('public_id', $code)->first()
            ?? AnimalIdentifier::with('animal')->where('value', $code)->whereNull('retired_at')->first()?->animal;
    }
}
