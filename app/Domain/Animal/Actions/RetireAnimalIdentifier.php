<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\AnimalIdentifier;
use App\Domain\System\Exceptions\DomainException;

class RetireAnimalIdentifier
{
    public function __invoke(AnimalIdentifier $identifier, string $reason): AnimalIdentifier
    {
        if ($identifier->isRetired()) {
            throw new DomainException('This identifier is already retired.', 'identifier_retired');
        }

        if (trim($reason) === '') {
            throw new DomainException('A reason is required to retire an identifier (for example: tag lost).', 'reason_required');
        }

        $identifier->forceFill(['retired_at' => now(), 'retired_reason' => trim($reason)])->save();

        return $identifier;
    }
}
