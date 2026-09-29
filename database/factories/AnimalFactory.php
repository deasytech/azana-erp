<?php

namespace Database\Factories;

use App\Domain\Animal\Models\Animal;
use App\Domain\Farm\Models\LookupValue;
use App\Enums\AnimalSex;
use App\Enums\AnimalSource;
use App\Enums\LookupCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Test data only: real animals are created with the RegisterAnimal action. */
class AnimalFactory extends Factory
{
    protected $model = Animal::class;

    public function definition(): array
    {
        return [
            'animal_number' => 'TST-'.strtoupper(fake()->unique()->bothify('??####')),
            'sex' => AnimalSex::Female,
            'category_id' => fn () => LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', 'sow')->value('id'),
            'source' => AnimalSource::BornOnFarm,
        ];
    }
}
