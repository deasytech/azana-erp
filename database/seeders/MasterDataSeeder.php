<?php

namespace Database\Seeders;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Enums\LookupCategory as C;
use Illuminate\Database\Seeder;

/**
 * Starting master data. Idempotent (only creates what is missing) and safe in production:
 * everything here is editable in the admin UI afterwards.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->lookups();
        $this->units();
        $this->breeds();

        $farm = Farm::firstOrCreate(['code' => 'IPAF'], [
            'name' => 'Integrated Princess Azana Farms',
            'legal_name' => 'Integrated Princess Azana Farms Ltd',
        ]);

        $types = LookupValue::where('category', C::ProductionUnitType->value)->pluck('id', 'code');

        foreach ([
            ['PIG', 'piggery', 'Piggery'],
            ['FM', 'feed_mill', 'Feed Mill'],
            ['SEM', 'semen_laboratory', 'Semen Laboratory'],
            ['SLH', 'slaughterhouse', 'Slaughterhouse & Meat Processing'],
        ] as [$code, $type, $name]) {
            ProductionUnit::firstOrCreate(
                ['code' => $code],
                ['farm_id' => $farm->id, 'type_id' => $types[$type], 'name' => $name],
            );
        }

        app(ResolveSettings::class)->ensureDefaults($farm);
    }

    private function lookups(): void
    {
        $values = [
            C::ProductionUnitType->value => ['piggery' => 'Piggery', 'feed_mill' => 'Feed mill', 'semen_laboratory' => 'Semen laboratory', 'slaughterhouse' => 'Slaughterhouse / meat processing', 'store' => 'Store', 'other' => 'Other'],
            C::BuildingType->value => ['farrowing_house' => 'Farrowing house', 'gestation_house' => 'Gestation house', 'boar_house' => 'Boar house', 'nursery_house' => 'Nursery house', 'grower_finisher_house' => 'Grower / finisher house', 'quarantine' => 'Quarantine', 'store' => 'Store', 'office' => 'Office', 'other' => 'Other'],
            C::LocationType->value => ['store' => 'Store', 'cold_room' => 'Cold room', 'silo' => 'Silo / bin', 'quarantine' => 'Quarantine area', 'loading_bay' => 'Loading bay', 'laboratory' => 'Laboratory', 'other' => 'Other'],
            C::PenPurpose->value => ['boar' => 'Boar', 'gestation' => 'Gestation', 'farrowing' => 'Farrowing', 'nursery' => 'Nursery', 'grower' => 'Grower', 'finisher' => 'Finisher', 'gilt' => 'Gilt development', 'sick_bay' => 'Sick bay', 'quarantine' => 'Quarantine', 'holding' => 'Holding'],
            C::AnimalCategory->value => ['sow' => 'Sow', 'boar' => 'Boar', 'gilt' => 'Gilt', 'piglet' => 'Piglet', 'weaner' => 'Weaner', 'grower' => 'Grower', 'finisher' => 'Finisher'],
            C::MovementReason->value => ['weaning' => 'Weaning', 'stage_change' => 'Production stage change', 'breeding' => 'Breeding', 'farrowing' => 'Farrowing', 'isolation' => 'Isolation / sick bay', 'overcrowding' => 'Overcrowding', 'correction' => 'Correction', 'other' => 'Other'],
            C::PriceCategory->value => ['pig' => 'Pig sales', 'semen' => 'Semen', 'meat' => 'Meat', 'feed' => 'Feed', 'other' => 'Other'],
        ];

        foreach ($values as $category => $items) {
            $order = 0;
            foreach ($items as $code => $name) {
                LookupValue::firstOrCreate(
                    ['category' => $category, 'code' => $code],
                    ['name' => $name, 'sort_order' => $order += 10],
                );
            }
        }
    }

    private function units(): void
    {
        $base = [
            ['KG', 'Kilogram', 'mass'], ['L', 'Litre', 'volume'], ['HEAD', 'Head', 'count'],
            ['DOSE', 'Dose', 'count'], ['BAG', 'Bag', 'count'], ['PCS', 'Piece', 'count'],
            ['M', 'Metre', 'length'],
        ];

        foreach ($base as [$code, $name, $category]) {
            UnitOfMeasure::firstOrCreate(['code' => $code], ['name' => $name, 'category' => $category]);
        }

        $kg = UnitOfMeasure::firstWhere('code', 'KG');
        $litre = UnitOfMeasure::firstWhere('code', 'L');

        foreach ([['G', 'Gram', 'mass', $kg, '0.001'], ['TON', 'Tonne', 'mass', $kg, '1000'], ['ML', 'Millilitre', 'volume', $litre, '0.001']] as [$code, $name, $category, $baseUnit, $factor]) {
            UnitOfMeasure::firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'base_unit_id' => $baseUnit->id, 'conversion_factor' => $factor,
            ]);
        }
    }

    private function breeds(): void
    {
        foreach ([
            ['LW', 'Large White'], ['LR', 'Landrace'], ['DUR', 'Duroc'],
            ['HAM', 'Hampshire'], ['PIE', 'Pietrain'], ['CROSS', 'Crossbred'],
        ] as [$code, $name]) {
            Breed::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
