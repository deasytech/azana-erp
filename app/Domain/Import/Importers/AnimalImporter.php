<?php

namespace App\Domain\Import\Importers;

use App\Domain\Animal\Actions\LookupAnimal;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\GeneticLine;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Import\Support\ImportColumn;
use App\Domain\Import\Support\Importer;
use App\Enums\AnimalSex;
use App\Enums\AnimalSource;
use App\Enums\LookupCategory;
use App\Models\User;

/**
 * The pigs on the farm today, registered through RegisterAnimal: each gets its permanent number, its tags go in as
 * identifiers, and it is placed in its pen. Animals that already left the farm are history, not registry entries.
 */
class AnimalImporter extends Importer
{
    public function __construct(
        private readonly RegisterAnimal $register,
        private readonly RecordWeight $weigh,
        private readonly LookupAnimal $lookup,
    ) {}

    public function key(): string
    {
        return 'animals';
    }

    public function label(): string
    {
        return 'Pigs on the farm (historical herd)';
    }

    public function description(): string
    {
        return 'The animals that are on the farm now. Each is given its permanent number; the tags you already use are stored as identifiers (a tag already on another animal is rejected). List parents before their offspring.';
    }

    public function module(): string
    {
        return 'animals';
    }

    public function columns(): array
    {
        return [
            new ImportColumn('sex', 'male or female.', true, 'female'),
            new ImportColumn('category', 'An animal category from the lists (its name or code).', true, 'Sow'),
            new ImportColumn('birth_date', 'YYYY-MM-DD. Leave blank if unknown.', false, '2024-03-15'),
            new ImportColumn('birth_date_estimated', 'yes if the birth date is a guess.', false, 'no'),
            new ImportColumn('breed', 'A breed (its name or code).', false, 'Large White'),
            new ImportColumn('genetic_line', 'A genetic line (its name or code).'),
            new ImportColumn('source', 'born_on_farm, purchased, transferred_in or other. Blank means born_on_farm.', false, 'purchased'),
            new ImportColumn('acquired_on', 'YYYY-MM-DD the pig arrived, for bought-in animals.'),
            new ImportColumn('source_name', 'Where it came from.'),
            new ImportColumn('purchase_price', 'Price paid, in naira (1250.50).'),
            new ImportColumn('pen', 'The pen it is in now (its code or name). Blank leaves it unplaced.', false, 'PEN-0003'),
            new ImportColumn('ear_tag', 'Its ear tag.', false, 'A-1042'),
            new ImportColumn('rfid', 'Its RFID number.'),
            new ImportColumn('tattoo', 'Its tattoo.'),
            new ImportColumn('sire', 'Father: an animal number or tag already registered.'),
            new ImportColumn('dam', 'Mother: an animal number or tag already registered.'),
            new ImportColumn('weight_kg', 'Last weight in kg (2 decimals).', false, '182.50'),
            new ImportColumn('weight_date', 'YYYY-MM-DD of that weighing. Required with the weight.'),
            new ImportColumn('notes', 'Anything worth remembering.'),
        ];
    }

    public function save(array $rows, ?User $actor): void
    {
        $row = $rows[0];
        $sex = $this->choice($row, 'sex', array_map(fn ($c) => $c->value, AnimalSex::cases()), true);
        $category = $this->find(LookupValue::class, $row, 'category', fn ($q) => $q->where('category', LookupCategory::AnimalCategory->value), true, 'animal category', 'animal_category');
        $breed = $this->find(Breed::class, $row, 'breed', label: 'breed');
        $line = $this->find(GeneticLine::class, $row, 'genetic_line', label: 'genetic line');
        $pen = $this->find(Pen::class, $row, 'pen', label: 'pen');
        $source = $this->choice($row, 'source', array_map(fn ($c) => $c->value, AnimalSource::cases())) ?? AnimalSource::BornOnFarm->value;
        $birth = $this->date($row, 'birth_date');
        $acquired = $this->date($row, 'acquired_on');
        $price = $this->minor($row, 'purchase_price');
        $weight = $this->decimal($row, 'weight_kg', 2);
        $weighedOn = $this->date($row, 'weight_date');

        ($weight === null) === ($weighedOn === null) || throw $this->problem('weight_date', 'and weight_kg go together: give both or neither');

        $identifiers = [];

        foreach (['ear_tag' => 'ear_tag', 'rfid' => 'rfid', 'tattoo' => 'tattoo'] as $column => $type) {
            if (isset($row[$column])) {
                $identifiers[] = ['type' => $type, 'value' => $row[$column]];
            }
        }

        $animal = ($this->register)(array_filter([
            'sex' => $sex, 'category_id' => $category->id, 'breed_id' => $breed?->id, 'genetic_line_id' => $line?->id,
            'birth_date' => $birth?->toDateString(), 'birth_date_estimated' => $this->flag($row, 'birth_date_estimated', false),
            'source' => $source, 'acquired_on' => $acquired?->toDateString(), 'source_name' => $row['source_name'] ?? null,
            'purchase_price_minor' => $price, 'pen_id' => $pen?->id, 'identifiers' => $identifiers,
            'sire_id' => $this->parent($row, 'sire'), 'dam_id' => $this->parent($row, 'dam'), 'notes' => $row['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== []), $actor);

        if ($weight !== null) {
            ($this->weigh)($animal, $weight, $weighedOn, 'scale', 'Imported', $actor);
        }
    }

    private function parent(array $row, string $column): ?int
    {
        $code = $row[$column] ?? null;

        return $code === null ? null : (($this->lookup)($code)?->id ?? throw $this->problem($column, "\"{$code}\" is not an animal already registered (list parents above their offspring)"));
    }
}
