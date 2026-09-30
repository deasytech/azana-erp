<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Animal\Models\AnimalParentage;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalSex;

class SetAnimalParentage
{
    private const MAX_GENERATIONS = 25;

    public function __invoke(Animal $animal, ?int $sireId, ?int $damId, ?string $sireNote = null, ?string $damNote = null, ?int $litterId = null): AnimalParentage
    {
        $this->assertParent($animal, $sireId, AnimalSex::Male, 'sire');
        $this->assertParent($animal, $damId, AnimalSex::Female, 'dam');

        return AnimalParentage::updateOrCreate(['animal_id' => $animal->id], [
            'sire_id' => $sireId,
            'dam_id' => $damId,
            'sire_note' => $sireNote ?: null,
            'dam_note' => $damNote ?: null,
            ...($litterId ? ['litter_id' => $litterId] : []), // an existing litter link is never cleared
        ]);
    }

    private function assertParent(Animal $animal, ?int $parentId, AnimalSex $expected, string $role): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Animal::find($parentId) ?? throw new DomainException("The {$role} does not exist.", 'unknown_parent');

        if ($parent->is($animal)) {
            throw new DomainException('An animal cannot be its own parent.', 'parentage_self');
        }

        if ($parent->sex !== $expected) {
            throw new DomainException("The {$role} must be {$expected->value}.", 'parentage_sex');
        }

        if ($parent->birth_date && $animal->birth_date && $parent->birth_date->gte($animal->birth_date)) {
            throw new DomainException("The {$role} must be born before the offspring.", 'parentage_dates');
        }

        if ($this->isAncestor($animal->id, $parent->id)) {
            throw new DomainException("{$parent->animal_number} descends from this animal and cannot be its {$role}.", 'parentage_cycle');
        }
    }

    /** True when $candidateId appears anywhere above $ofId... i.e. $ofId is an ancestor of $candidateId. */
    private function isAncestor(int $ofId, int $candidateId): bool
    {
        $frontier = [$candidateId];

        for ($generation = 0; $generation < self::MAX_GENERATIONS && $frontier !== []; $generation++) {
            $parents = AnimalParentage::whereIn('animal_id', $frontier)->get(['sire_id', 'dam_id']);
            $frontier = $parents->flatMap(fn ($p) => [$p->sire_id, $p->dam_id])->filter()->unique()->values()->all();

            if (in_array($ofId, $frontier, true)) {
                return true;
            }
        }

        return false;
    }
}
