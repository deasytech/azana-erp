<?php

namespace App\Domain\Litter\Actions;

use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\Piglet;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\LookupCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registers individually tracked piglets of a litter as animals (linked to litter, dam and sire)
 * and records their birth weight. Untracked piglets stay as litter counts only.
 */
class RegisterLitterPiglets
{
    public function __construct(
        private readonly RegisterAnimal $registerAnimal,
        private readonly RecordWeight $recordWeight,
    ) {}

    /**
     * @param  list<array{sex: string, birth_weight_kg?: ?string, identifiers?: list<array{type: string, value: string}>}>  $piglets
     * @return Collection<int, Animal>
     */
    public function __invoke(Litter $litter, array $piglets, ?int $penId = null, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($litter, $piglets, $penId, $actor) {
            $litter = Litter::lockForUpdate()->with(['sow', 'farrowing'])->findOrFail($litter->id);

            if (! $litter->isSuckling()) {
                throw new DomainException("{$litter->litter_number} is already weaned.", 'litter_weaned');
            }

            $room = $litter->farrowing->born_alive - $litter->piglets()->count();

            if ($piglets === [] || count($piglets) > $room) {
                throw new DomainException("This litter has {$room} born-alive piglet(s) not yet registered.", 'litter_capacity');
            }

            $category = LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', 'piglet')->where('is_active', true)->value('id')
                ?? throw new DomainException('The "piglet" animal category is missing or inactive.', 'invalid_category');

            return collect($piglets)->map(fn (array $spec) => $this->registerOne($litter, $spec, $category, $penId, $actor));
        });
    }

    /** @param array{sex: string, birth_weight_kg?: ?string, identifiers?: list<array{type: string, value: string}>} $spec */
    private function registerOne(Litter $litter, array $spec, int $categoryId, ?int $penId, ?User $actor): Animal
    {
        $sow = $litter->sow;

        $animal = ($this->registerAnimal)([
            'sex' => $spec['sex'],
            'category_id' => $categoryId,
            'breed_id' => $sow->breed_id,
            'genetic_line_id' => $sow->genetic_line_id,
            'birth_date' => $litter->born_on->toDateString(),
            'source' => 'born_on_farm',
            'sire_id' => $litter->sire_id,
            'dam_id' => $sow->id,
            'litter_id' => $litter->id,
            'pen_id' => $penId,
            'identifiers' => $spec['identifiers'] ?? [],
        ], $actor);

        $weight = $spec['birth_weight_kg'] ?? null;
        $weight !== null && ($this->recordWeight)($animal, (string) $weight, $litter->born_on->copy(), 'birth', 'Birth weight', $actor);

        Piglet::create(['litter_id' => $litter->id, 'animal_id' => $animal->id, 'birth_weight_kg' => $weight]);

        return $animal;
    }
}
