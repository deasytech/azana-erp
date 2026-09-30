<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\System\Exceptions\DomainException;
use Carbon\CarbonInterface;

/**
 * The single guard for selling or slaughtering an animal: nothing under an active withdrawal
 * period or in quarantine may leave for the food chain. Sales and slaughter flows reach it through
 * ChangeAnimalStatus.
 */
class AssertAnimalCanEnterFoodChain
{
    public function __construct(private readonly GetAnimalRestrictions $restrictions) {}

    public function __invoke(Animal $animal, ?CarbonInterface $on = null): void
    {
        ['withdrawals' => $withdrawals, 'quarantine' => $quarantine] = ($this->restrictions)($animal, $on);

        if ($withdrawals->isNotEmpty()) {
            $latest = $withdrawals->first();

            throw new DomainException(
                "{$animal->animal_number} is under withdrawal for {$latest->medicine->name} until {$latest->ends_on->format('d M Y')} and cannot be sold or slaughtered.",
                'animal_under_withdrawal',
            );
        }

        if ($quarantine) {
            throw new DomainException("{$animal->animal_number} is in {$quarantine->type->value} and cannot be sold or slaughtered.", 'animal_in_quarantine');
        }
    }
}
