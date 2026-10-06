<?php

namespace Database\Seeders;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Meat\Models\MeatProduct;
use App\Enums\InventoryCategory;
use App\Enums\LookupCategory as C;
use App\Enums\MeatProductKind;
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
        $this->feedTypes();
        $this->inventoryLocations();
        $this->semenItems();
        $this->meatProducts();

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
            C::MedicineType->value => ['vaccine' => 'Vaccine', 'antibiotic' => 'Antibiotic', 'antiparasitic' => 'Antiparasitic', 'anti_inflammatory' => 'Anti-inflammatory', 'vitamin_supplement' => 'Vitamin / supplement', 'hormone' => 'Hormone', 'other' => 'Other'],
            C::MortalityCause->value => ['crushed' => 'Crushed by sow', 'scours' => 'Scours / diarrhoea', 'respiratory' => 'Respiratory disease', 'disease_other' => 'Other disease', 'starvation' => 'Starvation / weak', 'injury' => 'Injury', 'euthanised' => 'Euthanised', 'unknown' => 'Unknown'],
            C::CullReason->value => ['poor_performance' => 'Poor performance', 'reproductive_failure' => 'Reproductive failure', 'lameness' => 'Lameness', 'age' => 'Age', 'disease' => 'Disease', 'injury' => 'Injury', 'low_weight' => 'Low weight / poor growth', 'other' => 'Other'],
            C::CustomerType->value => ['farmer' => 'Farmer', 'breeder' => 'Breeder', 'butcher' => 'Butcher / processor', 'retailer' => 'Retailer', 'institution' => 'Institution', 'individual' => 'Individual'],
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

    private function feedTypes(): void
    {
        foreach ([
            ['PRESTART', 'Pre-starter'], ['STARTER', 'Starter'], ['GROWER', 'Grower'], ['FINISHER', 'Finisher'],
            ['GESTATION', 'Sow gestation'], ['LACTATION', 'Sow lactation'], ['BOAR', 'Boar'],
        ] as [$code, $name]) {
            FeedType::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function inventoryLocations(): void
    {
        foreach ([['MAIN', 'Main store'], ['FEED', 'Feed store'], ['VET', 'Veterinary store'], ['SEMEN', 'Semen laboratory store'], ['COLD1', 'Cold room 1']] as [$code, $name]) {
            InventoryLocation::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    /** One semen stock item per breed, counted in doses and tracked by batch and expiry. */
    private function semenItems(): void
    {
        $dose = UnitOfMeasure::firstWhere('code', 'DOSE');

        foreach (Breed::orderBy('code')->get() as $breed) {
            InventoryItem::firstOrCreate(['code' => "SEMEN-{$breed->code}"], [
                'name' => "Semen dose - {$breed->name}", 'category' => InventoryCategory::Semen, 'unit_id' => $dose->id,
                'tracks_batches' => true, 'tracks_expiry' => true, 'breed_id' => $breed->id,
            ]);
        }
    }

    /** The cuts and by-products that come off a pig, each stocked in kg by batch with a use-by date. */
    private function meatProducts(): void
    {
        $kg = UnitOfMeasure::firstWhere('code', 'KG');

        foreach ([
            ['CARCASS', 'Whole carcass (dressed)', MeatProductKind::WholeCarcass, 5], ['LEG', 'Leg', MeatProductKind::PrimaryCut, 5],
            ['LOIN', 'Loin', MeatProductKind::PrimaryCut, 5], ['SHOULDER', 'Shoulder', MeatProductKind::PrimaryCut, 5],
            ['BELLY', 'Belly', MeatProductKind::PrimaryCut, 5], ['RIBS', 'Ribs', MeatProductKind::PrimaryCut, 5],
            ['LIVER', 'Liver', MeatProductKind::Offal, 3], ['HEAD', 'Head', MeatProductKind::ByProduct, 3],
            ['TROTTERS', 'Trotters', MeatProductKind::ByProduct, 3], ['FAT', 'Back fat', MeatProductKind::ByProduct, 7],
        ] as [$code, $name, $kind, $shelfLife]) {
            $item = InventoryItem::firstOrCreate(['code' => "MEAT-{$code}"], [
                'name' => $name, 'category' => InventoryCategory::Meat, 'unit_id' => $kg->id, 'tracks_batches' => true, 'tracks_expiry' => true,
            ]);

            MeatProduct::firstOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind, 'inventory_item_id' => $item->id, 'shelf_life_days' => $shelfLife]);
        }
    }
}
