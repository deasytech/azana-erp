<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Events\AnimalRegistered;
use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\GeneticLine;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalSex;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\IdentifierType;
use App\Enums\LookupCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registers an animal: permanent number, identifiers, parentage, first placement and
 * initial status history, all in one transaction.
 *
 * $data keys: sex, category_id, breed_id, genetic_line_id, birth_date, birth_date_estimated,
 * source, acquired_on, source_name, source_reference, purchase_price_minor, notes,
 * pen_id | location_id, identifiers[] {type, value, issued_on}, sire_id, dam_id, sire_note, dam_note, litter_id.
 */
class RegisterAnimal
{
    /** Category codes tied to one sex (the categories themselves are admin-editable lookups). */
    private const FEMALE_ONLY = ['sow', 'gilt'];

    private const MALE_ONLY = ['boar'];

    /** Fields copied straight from the input onto the animal. */
    private const PLAIN_FIELDS = [
        'breed_id', 'genetic_line_id', 'birth_date', 'birth_date_estimated', 'acquired_on',
        'source_name', 'source_reference', 'purchase_price_minor', 'notes',
    ];

    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly ResolveSettings $settings,
        private readonly AddAnimalIdentifier $addIdentifier,
        private readonly SetAnimalParentage $setParentage,
        private readonly RecordAnimalMovement $moveAnimal,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?User $actor = null): Animal
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($data, $actor) {
            $sex = AnimalSex::from($data['sex']);
            $category = $this->category($data['category_id'], $sex);
            $this->validateDetails($data);

            $animal = Animal::create([
                ...Arr::only($data, self::PLAIN_FIELDS),
                'animal_number' => $this->nextAnimalNumber($category),
                'sex' => $sex,
                'category_id' => $category->id,
                'source' => AnimalSource::from($data['source'] ?? AnimalSource::BornOnFarm->value),
                'status' => AnimalStatus::Active,
            ]);

            $animal->statusHistory()->create([
                'from_status' => null, 'to_status' => AnimalStatus::Active, 'changed_at' => now(),
                'reason' => 'Registered', 'user_id' => $actor?->getKey(),
            ]);

            foreach ($data['identifiers'] ?? [] as $identifier) {
                ($this->addIdentifier)($animal, IdentifierType::from($identifier['type']), $identifier['value'], isset($identifier['issued_on']) ? Carbon::parse($identifier['issued_on']) : null);
            }

            $this->applyParentage($animal, $data);

            if (! empty($data['pen_id']) || ! empty($data['location_id'])) {
                ($this->moveAnimal)($animal, $data['pen_id'] ?? null, $data['location_id'] ?? null, null, null, 'Initial placement', $actor);
            }

            AnimalRegistered::dispatch($animal);

            return $animal->refresh();
        });
    }

    private function category(int $categoryId, AnimalSex $sex): LookupValue
    {
        $category = LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('is_active', true)->find($categoryId)
            ?? throw new DomainException('Choose a valid animal category.', 'invalid_category');

        if (($sex === AnimalSex::Male && in_array($category->code, self::FEMALE_ONLY, true))
            || ($sex === AnimalSex::Female && in_array($category->code, self::MALE_ONLY, true))) {
            throw new DomainException("A {$sex->value} animal cannot be registered as a {$category->name}.", 'category_sex');
        }

        return $category;
    }

    /** @param array<string, mixed> $data */
    private function validateDetails(array $data): void
    {
        $breedId = $data['breed_id'] ?? null;
        $lineId = $data['genetic_line_id'] ?? null;
        $birthDate = $data['birth_date'] ?? null;
        $acquiredOn = $data['acquired_on'] ?? null;

        $breed = $breedId ? Breed::where('is_active', true)->find($breedId) ?? throw new DomainException('Choose a valid breed.', 'invalid_breed') : null;

        if ($lineId) {
            $line = GeneticLine::where('is_active', true)->find($lineId) ?? throw new DomainException('Choose a valid genetic line.', 'invalid_line');

            if ($breed && $line->breed_id && $line->breed_id !== $breed->id) {
                throw new DomainException('The genetic line belongs to a different breed.', 'line_breed');
            }
        }

        if ($birthDate && Carbon::parse($birthDate)->isFuture()) {
            throw new DomainException('The birth date cannot be in the future.', 'birth_future');
        }

        if ($acquiredOn && Carbon::parse($acquiredOn)->isFuture()) {
            throw new DomainException('The acquisition date cannot be in the future.', 'acquired_future');
        }
    }

    private function nextAnimalNumber(LookupValue $category): string
    {
        $prefix = strtoupper((string) $this->settings->get('animals.number_prefix'));
        $stem = $prefix.'-'.strtoupper($category->code);

        return sprintf('%s-%04d', $stem, ($this->nextNumber)("animal:{$stem}"));
    }

    /** @param array<string, mixed> $data */
    private function applyParentage(Animal $animal, array $data): void
    {
        $keys = ['sire_id', 'dam_id', 'sire_note', 'dam_note', 'litter_id'];

        if (array_filter(array_intersect_key($data, array_flip($keys)))) {
            ($this->setParentage)($animal, $data['sire_id'] ?? null, $data['dam_id'] ?? null, $data['sire_note'] ?? null, $data['dam_note'] ?? null, $data['litter_id'] ?? null);
        }
    }
}
