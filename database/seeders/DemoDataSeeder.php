<?php

namespace Database\Seeders;

use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\RegisterAnimal;
use App\Domain\Animal\Models\Animal;
use App\Domain\Biosecurity\Actions\RecordBiosecurityCheck;
use App\Domain\Biosecurity\Actions\RecordVisitorArrival;
use App\Domain\Biosecurity\Actions\RecordVisitorDeparture;
use App\Domain\Biosecurity\Models\BiosecurityChecklistItem;
use App\Domain\Breeding\Actions\RecordFarrowing;
use App\Domain\Breeding\Actions\RecordHeat;
use App\Domain\Breeding\Actions\RecordPregnancyCheck;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Farm\Models\Building;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Farm\Models\Pen;
use App\Domain\Farm\Models\ProductionUnit;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Actions\CompleteFeedProduction;
use App\Domain\Feed\Actions\ConfirmFeedConsumption;
use App\Domain\Feed\Actions\CreateFeedProductionOrder;
use App\Domain\Feed\Actions\ManageFeedFormulaVersions;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Finance\Actions\DecideJournal;
use App\Domain\Finance\Actions\PostManualJournal;
use App\Domain\Finance\Actions\RecordCashTransaction;
use App\Domain\Finance\Actions\RecordExpense;
use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\Finance\Actions\SyncOperationalPostings;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\CostCentre;
use App\Domain\Health\Actions\RecordCulling;
use App\Domain\Health\Actions\RecordLabResult;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Health\Actions\RecordTreatment;
use App\Domain\Health\Actions\RecordVaccination;
use App\Domain\Health\Actions\RecordVeterinaryVisit;
use App\Domain\Health\Actions\ReleaseQuarantine;
use App\Domain\Health\Actions\ReportHealthEvent;
use App\Domain\Health\Actions\ResolveHealthEvent;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Health\Models\Disease;
use App\Domain\Health\Models\HealthEvent;
use App\Domain\Health\Models\Medicine;
use App\Domain\Health\Models\MedicineBatch;
use App\Domain\Inventory\Actions\ApproveStockCount;
use App\Domain\Inventory\Actions\GetExpiryAlerts;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\RecordCountLine;
use App\Domain\Inventory\Actions\RequestStockAdjustment;
use App\Domain\Inventory\Actions\StartStockCount;
use App\Domain\Inventory\Actions\SubmitStockCount;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Litter\Actions\RecordLitterLoss;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\Litter\Models\Litter;
use App\Domain\Meat\Actions\ProduceMeat;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Procurement\Actions\CreatePurchaseOrder;
use App\Domain\Procurement\Actions\CreatePurchaseRequest;
use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\DecidePurchaseRequest;
use App\Domain\Procurement\Actions\DecideSupplierPayment;
use App\Domain\Procurement\Actions\ReceiveGoods;
use App\Domain\Procurement\Actions\RecordSupplierInvoice;
use App\Domain\Procurement\Actions\RecordSupplierPayment;
use App\Domain\Procurement\Models\SupplierInvoice;
use App\Domain\Production\Actions\OpenProductionBatch;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Actions\RecordProductionCost;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Reporting\Actions\SetKpiTarget;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Sales\Actions\DispatchSalesOrder;
use App\Domain\Sales\Actions\RecordCustomerPayment;
use App\Domain\Sales\Actions\SaveCustomer;
use App\Domain\Sales\Actions\SetCustomerCredit;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Semen\Actions\ExpireSemenBatches;
use App\Domain\Semen\Actions\ManageSemenBoar;
use App\Domain\Semen\Actions\ProcessSemenBatch;
use App\Domain\Semen\Actions\RecordSemenCollection;
use App\Domain\Semen\Actions\RecordSemenQc;
use App\Domain\Semen\Actions\ReleaseSemenBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Slaughter\Actions\ManageSlaughterBatch;
use App\Domain\Slaughter\Actions\RecordSlaughter;
use App\Domain\Slaughter\Actions\RecordSlaughterIntake;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Tasks\Actions\AdvanceTask;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Actions\GenerateDailyTasks;
use App\Domain\Tasks\Models\Task;
use App\Domain\Website\Actions\SaveListing;
use App\Domain\Website\Actions\SubmitEnquiry;
use App\Enums\AnteMortemResult;
use App\Enums\CashDirection;
use App\Enums\CreditStatus;
use App\Enums\CullHealthStatus;
use App\Enums\DataMode;
use App\Enums\DisposalType;
use App\Enums\HealthEventKind;
use App\Enums\HealthSeverity;
use App\Enums\InventoryCategory;
use App\Enums\InventoryTransactionType;
use App\Enums\LookupCategory as L;
use App\Enums\PaymentMethod;
use App\Enums\PostMortemResult;
use App\Enums\PregnancyCheckMethod;
use App\Enums\PregnancyCheckResult;
use App\Enums\ProductionCostCategory;
use App\Enums\QuarantineType;
use App\Enums\ReceiptMethod;
use App\Enums\ServiceMethod;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * A believable three months of farm life for trying out the whole app: boars and sows, semen, matings, litters, weaners,
 * growers, feed milled from purchased raw materials, vet work, biosecurity, slaughter, meat, sales, receipts, expenses, tasks.
 *
 * Everything goes through the same domain actions the screens use, day by day on a faked clock, so stock, books and traceability
 * stay consistent (the reconciliation on the Backups & monitoring page should be green afterwards). Money is in minor units (kobo).
 *
 * Run it with `php artisan db:seed --class=DemoDataSeeder` on an empty database. Remove it all again with
 * Administration > Go-live data reset. It refuses to run in production, or on a database that already holds animals.
 */
class DemoDataSeeder extends Seeder
{
    public const DAYS = 91;

    /** @var array<string, list<string>> */
    private array $failures = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, list<callable>> work booked for a future day, keyed by Y-m-d */
    private array $agenda = [];

    /** @var array<string, User> */
    private array $staff = [];

    /** @var list<array{animal: Animal, state: string, service: ?BreedingService, litter: ?Litter}> */
    private array $sows = [];

    /** @var list<Animal> */
    private array $boars = [];

    /** @var list<array{batch: SemenBatch, left: int}> */
    private array $semenStock = [];

    /** @var list<ProductionBatch> */
    private array $pigBatches = [];

    /** @var list<Customer> */
    private array $customers = [];

    /** @var array<string, mixed> */
    private array $ref = [];

    private CarbonInterface $start;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('The demo data seeder never runs in production.');

            return;
        }

        $this->call([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);

        if (Animal::query()->exists()) {
            $this->command?->error('This database already holds animals. Run Administration > Go-live data reset first, or use a fresh database.');

            return;
        }

        config(['queue.default' => 'sync', 'mail.default' => 'array']);
        mt_srand(20260709);
        $this->start = now()->subDays(self::DAYS)->setTime(8, 0);

        try {
            Carbon::setTestNow($this->start);
            $this->staff();
            Auth::login($this->staff['owner']);
            app(ResolveSettings::class)->set('system.data_mode', DataMode::Demo->value);
            $this->structure();
            $this->partners();
            $this->stores();
            $this->herd();
            $this->finance();
            $this->openingStock();
            $this->buyRawMaterials($this->start);

            for ($day = 0; $day <= self::DAYS; $day++) {
                $date = $this->start->copy()->addDays($day);
                Carbon::setTestNow($date->copy()->setTime(9, 0));
                $this->runDay($date, $day);
            }

            Carbon::setTestNow(now()->setTime(18, 0));
            $this->attempt('ledger postings', fn () => app(SyncOperationalPostings::class)());
            $this->leaveSomeWaiting();
        } finally {
            Carbon::setTestNow();
            Auth::logout();
        }

        $this->report();
    }

    // ---------------------------------------------------------------- set-up

    private function staff(): void
    {
        $roles = [
            'owner' => Role::OWNER, 'gm' => 'General Manager', 'farm' => 'Farm Manager', 'breeding' => 'Breeding Manager',
            'vet' => 'Veterinarian', 'lab' => 'Semen Laboratory Manager', 'mill' => 'Feed Mill Manager', 'store' => 'Store Officer',
            'sales' => 'Sales Officer', 'slaughter' => 'Slaughter Manager', 'accountant' => 'Accountant', 'worker' => 'Farm Worker',
        ];

        foreach ($roles as $key => $role) {
            $this->staff[$key] = User::role($role)->where('is_active', true)->first()
                ?? User::factory()->create(['name' => $role, 'email' => Str::slug($role).'@azana.test'])->assignRole($role);
        }
    }

    private function lookup(L $category, string $code): int
    {
        return LookupValue::where('category', $category->value)->where('code', $code)->value('id');
    }

    private function structure(): void
    {
        $unit = ProductionUnit::firstWhere('code', 'PIG');
        $layout = [
            ['FH', 'Farrowing house', 'farrowing_house', 'farrowing', 8], ['GH', 'Gestation house', 'gestation_house', 'gestation', 12],
            ['BH', 'Boar house', 'boar_house', 'boar', 4], ['NH', 'Nursery house', 'nursery_house', 'nursery', 4],
            ['GF', 'Grower / finisher house', 'grower_finisher_house', 'grower', 6], ['QH', 'Quarantine house', 'quarantine', 'quarantine', 2],
        ];

        foreach ($layout as [$code, $name, $type, $purpose, $pens]) {
            $building = Building::firstOrCreate(['code' => $code], ['production_unit_id' => $unit->id, 'type_id' => $this->lookup(L::BuildingType, $type), 'name' => $name]);

            for ($i = 1; $i <= $pens; $i++) {
                Pen::firstOrCreate(['code' => sprintf('%s-%02d', $code, $i)], ['building_id' => $building->id, 'purpose_id' => $this->lookup(L::PenPurpose, $purpose), 'capacity' => ['grower' => 60, 'farrowing' => 24, 'nursery' => 60, 'gestation' => 30, 'boar' => 6][$purpose] ?? 10]);
            }
        }

        foreach ([['NEWC', 'Newcastle disease', false], ['ASF', 'African swine fever', true], ['PRRS', 'PRRS', true], ['SCOUR', 'Scours', false], ['MMA', 'Mastitis-metritis-agalactia', false], ['PNEUM', 'Pneumonia', false]] as [$code, $name, $reportable]) {
            Disease::firstOrCreate(['code' => $code], ['name' => $name, 'is_reportable' => $reportable]);
        }

        foreach ([
            ['GATE', 'Vehicles disinfected at the gate', 'Entrance'], ['FOOT', 'Footbaths topped up and clean', 'Entrance'], ['PPE', 'Staff changed into farm clothing', 'Entrance'],
            ['VISIT', 'Visitor log completed', 'Entrance'], ['PEST', 'No rodent or bird entry points', 'Buildings'], ['WASTE', 'Mortality bin sealed and waste removed', 'Buildings'],
        ] as $sort => [$code, $text, $area]) {
            BiosecurityChecklistItem::firstOrCreate(['code' => $code], ['description' => $text, 'area' => $area, 'sort_order' => ($sort + 1) * 10]);
        }

        $this->ref['pens'] = Pen::with('building')->get()->groupBy(fn ($p) => $p->building->code)->map->values();
    }

    private function pen(string $building, int $i = 0): int
    {
        $pens = $this->ref['pens'][$building];

        return $pens[$i % $pens->count()]->id;
    }

    private function partners(): void
    {
        foreach ([
            ['GRN', 'Greenfield Grains Ltd', 'Musa Bello', 14], ['PRX', 'AgroVita Premix & Additives', 'Ngozi Eze', 30],
            ['VET', 'Savannah Veterinary Supplies', 'Dr. Tunde Adeyemi', 21], ['EQP', 'FarmTech Equipment', 'Chidi Obi', 30],
        ] as [$code, $name, $contact, $terms]) {
            $this->ref['supplier'][$code] = Supplier::firstOrCreate(['code' => $code], [
                'name' => $name, 'contact_name' => $contact, 'phone' => '080'.mt_rand(10000000, 99999999), 'payment_terms_days' => $terms,
            ]);
        }

        $save = app(SaveCustomer::class);
        $approve = app(SetCustomerCredit::class);

        foreach ([
            ['City Meats & Sausages', 'butcher', 40000000000, 30, true], ['Lekki Fresh Mart', 'retailer', 20000000000, 14, true],
            ['Ogun Pig Breeders Co-op', 'breeder', 30000000000, 30, true], ['Hilltop Farms', 'farmer', 5000000000, 14, true],
            ['Mama Bisi Kitchen', 'individual', 0, 0, false], ['Abuja Hotels Group', 'institution', 50000000000, 45, true],
            ['Sunrise Farmers Union', 'farmer', 1500000000, 14, true], ['Walk-in customers', 'individual', 0, 0, false],
        ] as [$name, $type, $limit, $terms, $credit]) {
            $customer = $save(['name' => $name, 'customer_type_id' => $this->lookup(L::CustomerType, $type), 'phone' => '081'.mt_rand(10000000, 99999999), 'address' => 'Ibadan, Oyo State']);
            $credit && $approve($customer, CreditStatus::Approved, $limit, $terms, $this->staff['farm']);
            $this->customers[] = $customer->refresh();
        }
    }

    private function item(string $code, string $name, InventoryCategory $category, array $extra = []): InventoryItem
    {
        return InventoryItem::firstOrCreate(['code' => $code], $extra + [
            'name' => $name, 'category' => $category, 'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id,
        ]);
    }

    private function stores(): void
    {
        foreach ([['RAW', 'Raw material store'], ['FIN', 'Finished feed store']] as [$code, $name]) {
            $this->ref['loc'][$code] = InventoryLocation::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (['MAIN', 'FEED', 'VET', 'SEMEN', 'COLD1'] as $code) {
            $this->ref['loc'][$code] = InventoryLocation::firstWhere('code', $code);
        }

        $this->ref['item'] = [
            'maize' => $this->item('MAIZE', 'Maize (grain)', InventoryCategory::FeedIngredient, ['reorder_level' => 3000, 'reorder_quantity' => 8000]),
            'soya' => $this->item('SOYA', 'Soya bean meal', InventoryCategory::FeedIngredient, ['reorder_level' => 1500, 'reorder_quantity' => 4000]),
            'premix' => $this->item('PREMIX', 'Grower premix', InventoryCategory::FeedIngredient, ['tracks_batches' => true, 'tracks_expiry' => true, 'reorder_level' => 300, 'reorder_quantity' => 800]),
            'grower' => $this->item('GROWER-MEAL', 'Grower meal', InventoryCategory::FinishedFeed, ['feed_type_id' => FeedType::firstWhere('code', 'GROWER')->id, 'tracks_expiry' => true]),
            'finisher' => $this->item('FINISHER-MEAL', 'Finisher meal', InventoryCategory::FinishedFeed, ['feed_type_id' => FeedType::firstWhere('code', 'FINISHER')->id, 'tracks_expiry' => true]),
        ];

        $formula = fn (string $code, string $name, string $type, array $mix) => app(ManageFeedFormulaVersions::class)->activate(app(SaveFeedFormula::class)([
            'code' => $code, 'name' => $name, 'feed_type_id' => FeedType::firstWhere('code', $type)->id, 'process_loss_percent' => '2',
            'crude_protein_percent' => $type === 'GROWER' ? '17.0' : '14.5', 'energy_kcal_per_kg' => '3150',
            'items' => collect($mix)->map(fn ($pct, $key) => ['inventory_item_id' => $this->ref['item'][$key]->id, 'inclusion_percent' => $pct])->values()->all(),
        ]));

        $this->ref['formula']['grower'] = $formula('grower-std', 'Grower standard', 'GROWER', ['maize' => '60', 'soya' => '30', 'premix' => '10']);
        $this->ref['formula']['finisher'] = $formula('finisher-std', 'Finisher standard', 'FINISHER', ['maize' => '70', 'soya' => '22', 'premix' => '8']);

        foreach ([
            ['VAC-PARVO', 'Parvovirus / Erysipelas vaccine', 'vaccine', 0], ['VAC-PRRS', 'PRRS vaccine', 'vaccine', 0], ['AMOX', 'Amoxicillin injectable', 'antibiotic', 14],
            ['IRON', 'Iron dextran', 'vitamin_supplement', 0], ['IVER', 'Ivermectin', 'antiparasitic', 21], ['OXYT', 'Oxytocin', 'hormone', 0],
        ] as [$code, $name, $type, $withdrawal]) {
            $medicine = Medicine::firstOrCreate(['code' => $code], ['name' => $name, 'type_id' => $this->lookup(L::MedicineType, $type), 'default_withdrawal_days' => $withdrawal]);
            $this->ref['medicine'][$code] = $medicine;
            $this->ref['medicineBatch'][$code] = MedicineBatch::firstOrCreate(['medicine_id' => $medicine->id, 'batch_number' => "{$code}-26A"], [
                'expiry_date' => now()->addMonths(10)->toDateString(), 'received_on' => now()->toDateString(), 'quantity_received' => 200,
            ]);
        }
    }

    /** What was already in the stores on the first day: some raw materials and a few days of finished feed. */
    private function openingStock(): void
    {
        $on = $this->start->copy()->startOfDay();

        foreach ([['maize', 'RAW', '5000', 33000, []], ['soya', 'RAW', '2000', 87000, []], ['premix', 'RAW', '600', 185000, ['batch_number' => 'PX-OPEN', 'expiry_date' => $on->copy()->addMonths(5)->toDateString()]],
            ['grower', 'FIN', '5000', 62000, ['batch_number' => 'FM-OPEN-G', 'expiry_date' => $on->copy()->addMonths(2)->toDateString()]],
            ['finisher', 'FIN', '6000', 60000, ['batch_number' => 'FM-OPEN-F', 'expiry_date' => $on->copy()->addMonths(2)->toDateString()]]] as [$item, $store, $kg, $cost, $extra]) {
            $this->attempt('opening stock', fn () => app(ReceiveStock::class)(InventoryTransactionType::Opening, $this->ref['item'][$item], $this->ref['loc'][$store], $kg, $on, ['unit_cost_minor' => $cost] + $extra));
        }
    }

    private function finance(): void
    {
        $bank = Account::firstWhere('code', '1010');
        $equity = Account::firstWhere('code', '3000');
        $this->ref['acc'] = Account::pluck('id', 'code');
        $this->ref['cc'] = CostCentre::pluck('id', 'code');

        $this->attempt('opening capital', fn () => app(RecordCashTransaction::class)($this->start->copy(), CashDirection::In, $bank, $equity, 25000000000, null, 'CAP-001', 'Owner capital introduced'));

        $lines = [];

        for ($month = 1; $month <= 12; $month++) {
            $lines[] = ['account_id' => $this->ref['acc']['4020'], 'month' => $month, 'amount_minor' => 1800000000];
            $lines[] = ['account_id' => $this->ref['acc']['4010'], 'month' => $month, 'amount_minor' => 600000000];
            $lines[] = ['account_id' => $this->ref['acc']['5000'], 'month' => $month, 'amount_minor' => 1500000000];
            $lines[] = ['account_id' => $this->ref['acc']['5300'], 'month' => $month, 'amount_minor' => 500000000];
            $lines[] = ['account_id' => $this->ref['acc']['5400'], 'month' => $month, 'amount_minor' => 150000000];
        }

        $this->attempt('budget', function () use ($lines) {
            $budget = app(SaveBudget::class)('Operating budget '.now()->year, now()->year, $lines);
            app(SaveBudget::class)->approve($budget, $this->staff['gm']);
        });

        foreach (range(max(1, now()->subDays(self::DAYS)->month), now()->month) as $month) {
            foreach (['sales.invoiced_minor' => '6000000000', 'semen.doses' => '300', 'slaughter.pigs' => '24', 'feed.produced_kg' => '16000'] as $kpi => $target) {
                $this->attempt('kpi targets', fn () => app(SetKpiTarget::class)($kpi, now()->year, $month, $target));
            }
        }

        foreach ([
            ['pigs', 'Weaner and grower pigs', 'Healthy weaners and growers from our own breeding herd.'], ['semen', 'Boar semen doses', 'Fresh extended semen from proven Duroc, Large White and Landrace boars.'],
            ['meat', 'Fresh pork cuts', 'Hygienically slaughtered pork, cut and chilled on the farm.'],
        ] as [$kind, $title, $summary]) {
            $this->attempt('website listing', fn () => app(SaveListing::class)(['kind' => $kind, 'title' => $title, 'summary' => $summary, 'is_published' => true]));
        }
    }

    // ------------------------------------------------------------------ herd

    private function register(array $data): Animal
    {
        $animal = app(RegisterAnimal::class)($data + ['source' => 'born_on_farm']);
        $this->counts['animals registered'] = ($this->counts['animals registered'] ?? 0) + 1;

        return $animal;
    }

    private function herd(): void
    {
        $breeds = Breed::whereIn('code', ['LW', 'LR', 'DUR', 'PIE'])->pluck('id', 'code');

        foreach (['DUR', 'LW', 'LR', 'PIE'] as $i => $code) {
            $boar = $this->register(['sex' => 'male', 'category_id' => $this->lookup(L::AnimalCategory, 'boar'), 'breed_id' => $breeds[$code], 'birth_date' => now()->subMonths(20 + $i)->toDateString(), 'pen_id' => $this->pen('BH', $i)]);
            app(ManageSemenBoar::class)->enrol($boar);
            $this->boars[] = $boar;
        }

        $sowCategory = $this->lookup(L::AnimalCategory, 'sow');

        for ($i = 0; $i < 24; $i++) {
            $sow = $this->register([
                'sex' => 'female', 'category_id' => $sowCategory, 'breed_id' => $breeds[$i % 2 ? 'LR' : 'LW'], 'birth_date' => now()->subMonths(14 + $i % 8)->toDateString(),
                'pen_id' => $this->pen('GH', intdiv($i, 3)),
            ]);
            $this->sows[] = ['animal' => $sow, 'state' => 'open', 'service' => null, 'litter' => null, 'next' => null];

            // Fourteen sows are already in the pipeline when the simulation starts, at different stages, so litters arrive from the first weeks.
            if ($i < 14) {
                $this->serve($i, $this->start->copy()->subDays(8 + $i * 7)->startOfDay(), false);
            }
        }

        foreach ([['Weaner batch W-1', 'weaner', 70, 58, '11.50', 'NH'], ['Grower batch G-1', 'grower', 110, 85, '32.00', 'GF'], ['Finisher batch F-1', 'finisher', 90, 128, '68.00', 'GF'], ['Finisher batch F-2', 'finisher', 70, 105, '54.00', 'GF']] as $i => [$name, $stage, $count, $ageDays, $weight, $building]) {
            $placed = $this->start->copy()->subDays($ageDays)->startOfDay();
            $batch = app(OpenProductionBatch::class)([
                'name' => $name, 'stage_id' => $this->lookup(L::AnimalCategory, $stage), 'started_on' => $placed, 'count' => $count, 'average_weight_kg' => '7.00',
                'unit_cost_minor' => 3500000, 'pen_id' => $this->pen($building, $i), 'placed_age_days' => 28, 'target_weight_kg' => '100.00', 'breed_id' => $breeds['LW'],
            ]);
            $this->ref['growth'][$batch->id] = ['w0' => 7.0, 'placed' => $placed, 'now' => (float) $weight];
            $this->pigBatches[] = $batch;
        }
    }

    // ------------------------------------------------------------------ days

    private function runDay(CarbonInterface $date, int $day): void
    {
        foreach ($this->agenda[$date->toDateString()] ?? [] as $job) {
            $job();
        }

        $dow = $date->dayOfWeekIso;   // 1 = Monday

        $this->attempt('expired semen removed', fn () => app(ExpireSemenBatches::class)());
        $this->writeOffExpired();
        $this->breeding($date, $day);
        $this->dailyFeed($date);
        $this->attempt('daily tasks', fn () => app(GenerateDailyTasks::class)($date->copy()));

        match ($dow) {
            1 => $this->mondayWork($date, $day),
            2 => $this->tuesdayWork($date, $day),
            3 => $this->wednesdayWork($date, $day),
            4 => $this->thursdayWork($date, $day),
            5 => $this->fridayWork($date, $day),
            default => null,
        };

        $date->day === 25 && $this->payroll($date);
        $date->day === 1 && $this->monthStart($date);
        in_array($day, [30, 61], true) && $this->stockCount($date);
        $this->incidentals($date, $day);
        $this->tasks($date);
    }

    /** Meat and other stock past its use-by date is written off, as the store officer would. */
    private function writeOffExpired(): void
    {
        foreach (app(GetExpiryAlerts::class)()->where('expired', true) as $alert) {
            $batch = $alert['batch'];

            if ($batch->item->category === InventoryCategory::Semen) {
                continue;   // the semen expiry action does these
            }

            $held = InventoryLayer::where('inventory_batch_id', $batch->id)->where('remaining_quantity', '>', 0)
                ->selectRaw('inventory_location_id, SUM(remaining_quantity) as qty')->groupBy('inventory_location_id')->get();

            foreach ($held as $row) {
                $this->attempt('expired stock written off', fn () => app(IssueStock::class)(InventoryTransactionType::Wastage, $batch->inventory_item_id, $row->inventory_location_id, bcadd((string) $row->qty, '0', 3), now()->startOfDay(), ['batch' => $batch->id, 'reason' => "Expired {$batch->batch_number}"]));
            }
        }
    }

    private function later(CarbonInterface $on, callable $job): void
    {
        $this->agenda[$on->toDateString()][] = $job;
    }

    // -------------------------------------------------------------- breeding

    private function serve(int $i, CarbonInterface $on, bool $live = true): void
    {
        $sow = $this->sows[$i]['animal'];
        $batchRow = collect($this->semenStock)->first(fn ($st) => $st['left'] >= 2 && Carbon::parse($st['batch']->expiry_date)->greaterThan($on));
        $techName = 'Emeka (AI technician)';
        $service = null;

        if ($live && $batchRow && mt_rand(1, 100) <= 70) {
            $service = $this->attempt('artificial inseminations', fn () => app(RecordService::class)($sow, ServiceMethod::ArtificialInsemination, $on, technicianName: $techName, semenBatchId: $batchRow['batch']->id, semenLocationId: $this->ref['loc']['SEMEN']->id, doses: 2));
            $service && $this->useDoses($batchRow['batch']->id, 2);
        }

        $service ??= $this->attempt('natural services', fn () => app(RecordService::class)($sow, ServiceMethod::Natural, $on, $this->boars[mt_rand(0, 3)]->id, technicianName: $techName));

        if (! $service) {
            return;
        }

        $this->counts['services'] = ($this->counts['services'] ?? 0) + 1;
        $this->sows[$i] = ['animal' => $sow, 'state' => 'served', 'service' => $service, 'litter' => null, 'next' => null];
        $id = $sow->id;

        $this->at($on->copy()->addDays(28), fn () => $this->pregnancyCheck($id, $on->copy()->addDays(28)));
    }

    /** Runs the job on the day, or straight away if that day is already behind us (the sows already in the pipeline). */
    private function at(CarbonInterface $on, callable $job): void
    {
        $on->lessThanOrEqualTo(now()) ? $job() : $this->later($on, $job);
    }

    private function useDoses(int $batchId, int $doses): void
    {
        foreach ($this->semenStock as &$s) {
            if ($s['batch']->id === $batchId) {
                $s['left'] -= $doses;
            }
        }
    }

    private function sowRow(int $animalId): ?int
    {
        foreach ($this->sows as $i => $row) {
            if ($row['animal']->id === $animalId) {
                return $i;
            }
        }

        return null;
    }

    private function pregnancyCheck(int $animalId, CarbonInterface $on): void
    {
        $i = $this->sowRow($animalId);

        if ($i === null || $this->sows[$i]['state'] !== 'served') {
            return;
        }

        $service = $this->sows[$i]['service'];
        $positive = mt_rand(1, 100) <= 87;
        $on = $on->copy()->startOfDay();

        $this->attempt('pregnancy checks', fn () => app(RecordPregnancyCheck::class)($service, $positive ? PregnancyCheckResult::Positive : PregnancyCheckResult::Negative, $on, PregnancyCheckMethod::Ultrasound));

        if ($positive) {
            $this->sows[$i]['state'] = 'pregnant';
            $this->at($service->serviced_on->copy()->addDays(114), fn () => $this->farrow($animalId));

            return;
        }

        $this->attempt('heat', fn () => app(RecordHeat::class)($this->sows[$i]['animal'], $on->copy(), 'Returned to heat'));
        $this->sows[$i]['state'] = 'open';
        $this->sows[$i]['service'] = null;
        $this->sows[$i]['next'] = $on->copy()->addDays(4);
    }

    private function farrow(int $animalId): void
    {
        $i = $this->sowRow($animalId);

        if ($i === null || $this->sows[$i]['state'] !== 'pregnant') {
            return;
        }

        $born = mt_rand(9, 15);
        $stillborn = mt_rand(0, 100) < 40 ? mt_rand(1, 2) : 0;
        $mummified = mt_rand(0, 100) < 20 ? 1 : 0;
        $alive = max(6, $born - $stillborn - $mummified);
        $born = $alive + $stillborn + $mummified;
        $on = now()->copy()->startOfDay();

        $litter = $this->attempt('farrowings', fn () => app(RecordFarrowing::class)($this->sows[$i]['animal'], [
            'farrowed_on' => $on, 'total_born' => $born, 'born_alive' => $alive, 'stillborn' => $stillborn, 'mummified' => $mummified,
            'total_birth_weight_kg' => number_format($alive * 1.45, 2, '.', ''), 'assisted' => mt_rand(0, 100) < 12,
        ]));

        if (! $litter) {
            return;
        }

        $this->counts['litters'] = ($this->counts['litters'] ?? 0) + 1;
        $this->sows[$i]['state'] = 'lactating';
        $this->sows[$i]['litter'] = $litter;

        $this->attempt('litter piglets', fn () => app(RegisterLitterPiglets::class)($litter, collect(range(1, $alive))->map(fn ($n) => [
            'sex' => $n % 2 ? 'male' : 'female', 'birth_weight_kg' => number_format(mt_rand(120, 180) / 100, 2, '.', ''),
        ])->all(), $this->pen('FH', $i)));

        $losses = mt_rand(0, 100) < 70 ? mt_rand(1, 2) : 0;

        if ($losses > 0 && $alive - $losses >= 5) {
            $this->at($on->copy()->addDays(mt_rand(2, 5)), function () use ($litter, $losses) {
                $this->attempt('litter losses', fn () => app(RecordLitterLoss::class)($litter->refresh(), $losses, now()->startOfDay(), causeId: $this->lookup(L::MortalityCause, 'crushed')));
            });
            $alive -= $losses;
        }

        $weanOn = $on->copy()->addDays(28);
        $weaned = $alive - (mt_rand(0, 100) < 40 ? 1 : 0);
        $this->at($weanOn, fn () => $this->wean($animalId, $litter, $weaned));
    }

    private function wean(int $animalId, Litter $litter, int $count): void
    {
        $on = now()->copy()->startOfDay();
        $weaned = $this->attempt('weanings', fn () => app(WeanLitter::class)($litter->refresh(), $on, $count, number_format($count * 7.4, 2, '.', ''), $this->pen('NH', $count)));

        if ($weaned) {
            $this->attempt('weaner batches', function () use ($litter, $on, $count) {
                $batch = app(OpenProductionBatch::class)([
                    'name' => 'Weaners '.$litter->number, 'stage_id' => $this->lookup(L::AnimalCategory, 'weaner'), 'started_on' => $on, 'count' => $count,
                    'average_weight_kg' => '7.40', 'unit_cost_minor' => 3200000, 'pen_id' => $this->pen('NH', $count), 'placed_age_days' => 28,
                ]);
                $this->ref['growth'][$batch->id] = ['w0' => 7.4, 'placed' => $on, 'now' => 7.4];
                $this->pigBatches[] = $batch;
            });
        }

        if (($i = $this->sowRow($animalId)) !== null && $this->sows[$i]['state'] === 'lactating') {
            $this->sows[$i]['state'] = 'open';
            $this->sows[$i]['service'] = null;
            $this->sows[$i]['next'] = $on->copy()->addDays(5);
        }
    }

    private function breeding(CarbonInterface $date, int $day): void
    {
        $served = 0;

        foreach (array_keys($this->sows) as $i) {
            if ($served >= 2 || $day < 3) {
                break;
            }

            $row = $this->sows[$i];

            if ($row['state'] === 'open' && ($row['next'] === null || $row['next']->lessThanOrEqualTo($date))) {
                $this->serve($i, $date->copy()->startOfDay());
                $served += $this->sows[$i]['state'] === 'served' ? 1 : 0;
            }
        }
    }

    // ----------------------------------------------------------------- semen

    private function collectSemen(CarbonInterface $date, array $boarIndexes): void
    {
        foreach (array_map(fn ($i) => $this->boars[$i], $boarIndexes) as $boar) {
            $batch = $this->attempt('semen collections', fn () => app(RecordSemenCollection::class)($boar, now(), (string) mt_rand(180, 320), ['technician_name' => 'Lab tech', 'ph' => '7.2']));

            if (! $batch) {
                continue;
            }

            $this->counts['semen collections'] = ($this->counts['semen collections'] ?? 0) + 1;
            $this->later($date->copy()->addDay(), function () use ($batch) {
                $pass = mt_rand(1, 100) <= 88;
                $this->attempt('semen qc', fn () => app(RecordSemenQc::class)($batch, $pass ? (string) mt_rand(78, 90) : '45', $pass ? (string) mt_rand(260, 340) : '120', $pass ? (string) mt_rand(5, 14) : '35', null, $this->staff['lab']));
                $batch->refresh();

                if (! $pass) {
                    return;
                }

                $this->attempt('semen processing', fn () => app(ProcessSemenBatch::class)($batch, min(app(ProcessSemenBatch::class)->maxDoses($batch), 22), '80', 'BTS'));
                $released = $this->attempt('semen release', fn () => app(ReleaseSemenBatch::class)($batch->refresh(), $this->staff['farm'], $this->ref['loc']['SEMEN']));

                $released && $this->semenStock[] = ['batch' => $released->refresh(), 'left' => (int) $released->doses_produced ?: 20];
            });
        }
    }

    // ------------------------------------------------------------------ feed

    private function dailyFeed(CarbonInterface $date): void
    {
        foreach ($this->pigBatches as $batch) {
            $batch->refresh();

            if (($count = $this->heads($batch)) < 1) {
                continue;
            }

            $perHead = match ($batch->stage?->code) {
                'weaner' => 0.9, 'grower' => 1.9, default => 2.6,
            };
            $kg = number_format($count * $perHead, 1, '.', '');
            $type = in_array($batch->stage?->code, ['finisher'], true) ? 'FINISHER' : 'GROWER';

            $this->attempt('pig feeding', fn () => app(RecordFeedConsumption::class)($batch, FeedType::firstWhere('code', $type), $date->copy()->startOfDay(), $kg, ['inventory_location_id' => $this->ref['loc']['FIN']->id]));
        }
    }

    private function runMill(string $which, string $kg): void
    {
        $order = $this->attempt('feed orders', fn () => app(CreateFeedProductionOrder::class)($this->ref['formula'][$which], $kg, now()->startOfDay(), $this->ref['loc']['RAW'], $this->ref['loc']['FIN']));

        if (! $order) {
            return;
        }

        $this->attempt('feed production', function () use ($order, $kg) {
            app(ConfirmFeedConsumption::class)->asPlanned($order);
            app(CompleteFeedProduction::class)($order, (string) round((float) $kg * 0.985), now()->startOfDay(), 1200000);
        });
        $this->counts['feed runs'] = ($this->counts['feed runs'] ?? 0) + 1;
    }

    // ------------------------------------------------------------- the week

    private function mondayWork(CarbonInterface $date, int $day): void
    {
        $this->buyRawMaterials($date);
        $this->collectSemen($date, [0, 1]);
        $this->vaccinate($date);
        $this->weighBatches($date);
        $this->biosecurityCheck($date);
        $this->visitors($date);
    }

    private function tuesdayWork(CarbonInterface $date, int $day): void
    {
        $this->runMill('grower', '3200');
        $this->vetVisit($date, $day);
        $this->visitors($date);
    }

    private function wednesdayWork(CarbonInterface $date, int $day): void
    {
        $this->collectSemen($date, [2, 3]);
        $this->sellMeat($date);
        $this->payInvoices($date);
        $this->sellSemen($date);
        $this->payBills($date);
    }

    private function thursdayWork(CarbonInterface $date, int $day): void
    {
        $this->runMill('finisher', '3400');
        $this->slaughter($date, $day);
        $this->sellPigs($date, $day);
        $this->expenses($date);
    }

    private function fridayWork(CarbonInterface $date, int $day): void
    {
        $this->makeMeat();
        $this->sellMeat($date);
        $this->sellSemen($date);
        $this->payInvoices($date);
        $this->weeklyCosts($date);
        $this->enquiry($date);
    }

    // ----------------------------------------------------------- procurement

    private function buyRawMaterials(CarbonInterface $date): void
    {
        $factor = $this->start->diffInDays($date) < 1 ? 2.0 : 1.0;
        $lines = [
            [$this->ref['supplier']['GRN'], 'maize', 5400 * $factor, 33500 + mt_rand(-1500, 2500)],
            [$this->ref['supplier']['GRN'], 'soya', 2300 * $factor, 88000 + mt_rand(-3000, 5000)],
            [$this->ref['supplier']['PRX'], 'premix', 680 * $factor, 190000 + mt_rand(-5000, 9000)],
        ];

        foreach ($lines as [$supplier, $key, $qty, $cost]) {
            $this->attempt('purchase orders', function () use ($supplier, $key, $qty, $cost, $date) {
                $item = $this->ref['item'][$key];
                $order = app(CreatePurchaseOrder::class)($supplier, [['inventory_item_id' => $item->id, 'quantity' => (string) (int) $qty, 'unit_cost_minor' => (int) $cost]], $date->copy()->startOfDay(), $date->copy()->addDay());
                app(DecidePurchaseOrder::class)->submit($order);
                app(DecidePurchaseOrder::class)->approve($order, $this->staff['gm']);
                $line = $order->refresh()->lines->first();
                $extra = $key === 'premix' ? ['batch_number' => 'PX-'.$date->format('md'), 'expiry_date' => $date->copy()->addMonths(6)->toDateString(), 'supplier_id' => $supplier->id] : [];
                $this->later($date->copy()->addDay(), function () use ($order, $line, $qty, $extra, $supplier) {
                    $this->attempt('goods receipts', fn () => app(ReceiveGoods::class)($order->refresh(), $this->ref['loc']['RAW'], now()->startOfDay(), [['purchase_order_line_id' => $line->id, 'quantity' => (string) (int) $qty] + $extra], 'DN-'.mt_rand(1000, 9999)));
                    $order->refresh();
                    $invoice = $this->attempt('supplier invoices', fn () => app(RecordSupplierInvoice::class)($order, 'SINV-'.mt_rand(10000, 99999), now()->startOfDay(), (int) $order->total_minor));

                    if ($invoice) {
                        $due = now()->copy()->addDays($supplier->payment_terms_days ?: 14);
                        $this->later($due, fn () => $this->paySupplier($invoice->id, $supplier));
                    }
                });
            });
        }
    }

    private function paySupplier(int $invoiceId, Supplier $supplier): void
    {
        $invoice = SupplierInvoice::find($invoiceId);
        $payment = $invoice ? $this->attempt('supplier payments', fn () => app(RecordSupplierPayment::class)($invoice, (int) $invoice->total_minor, now()->startOfDay(), PaymentMethod::BankTransfer, 'TRF-'.mt_rand(100000, 999999))) : null;

        if ($payment && $payment->status?->value === 'pending') {
            $this->attempt('supplier payment approvals', fn () => app(DecideSupplierPayment::class)->approve($payment, $this->staff['gm']));
        }
    }

    private function payBills(CarbonInterface $date): void {}

    // ---------------------------------------------------------------- health

    private function vaccinate(CarbonInterface $date): void
    {
        $med = $this->ref['medicine'][mt_rand(0, 1) ? 'VAC-PARVO' : 'VAC-PRRS'];
        $batch = $this->ref['medicineBatch'][$med->code];

        foreach (collect($this->sows)->shuffle()->take(4) as $row) {
            $this->attempt('vaccinations', fn () => app(RecordVaccination::class)($row['animal'], $date->copy()->startOfDay(), ['medicine_id' => $med->id, 'batch_id' => $batch->id, 'dose' => '2', 'notes' => 'Routine herd programme']));
        }

        foreach ($this->boars as $boar) {
            $this->attempt('vaccinations', fn () => app(RecordVaccination::class)($boar, $date->copy()->startOfDay(), ['medicine_id' => $med->id, 'batch_id' => $batch->id, 'dose' => '2']));
        }
    }

    private function vetVisit(CarbonInterface $date, int $day): void
    {
        $visit = $this->attempt('vet visits', fn () => app(RecordVeterinaryVisit::class)($date->copy()->startOfDay(), 'Routine herd health round', [
            'veterinarian_id' => $this->staff['vet']->id, 'findings' => 'Herd in good condition; two sows with mild lameness.', 'recommendations' => 'Footbath maintenance; review flooring in GH-03.',
            'cost_minor' => 4500000, 'follow_up_on' => $date->copy()->addDays(14)->startOfDay(),
        ]));

        if ($day % 14 === 2) {
            $this->attempt('lab results', fn () => app(RecordLabResult::class)('Blood', 'PRRS ELISA', $date->copy()->startOfDay(), [
                'result' => 'Negative', 'is_abnormal' => false, 'lab_name' => 'Ibadan Veterinary Diagnostics', 'resulted_on' => $date->copy()->addDays(2)->toDateString(), 'veterinary_visit_id' => $visit?->id,
            ]));
        }

        // A sow falls ill, is seen by the vet, treated (the antibiotic starts a withdrawal period) and recovers.
        $sow = $this->sows[mt_rand(0, count($this->sows) - 1)]['animal'];
        $event = $this->attempt('health events', fn () => app(ReportHealthEvent::class)($sow, HealthEventKind::Illness, HealthSeverity::Moderate, $date->copy()->startOfDay(), 'Off feed, raised temperature', Disease::firstWhere('code', 'MMA')->id, $visit?->id));

        if ($event) {
            $this->attempt('treatments', fn () => app(RecordTreatment::class)($sow, $this->ref['medicine']['AMOX'], $date->copy()->startOfDay(), ['dose' => '10', 'dose_unit' => 'ml', 'route' => 'IM', 'health_event_id' => $event->id, 'batch_id' => $this->ref['medicineBatch']['AMOX']->id]));
            $this->later($date->copy()->addDays(5), fn () => $this->attempt('health resolutions', fn () => app(ResolveHealthEvent::class)(HealthEvent::find($event->id), now()->startOfDay(), 'Recovered after treatment')));
        }
    }

    // ---------------------------------------------------------- biosecurity

    private function biosecurityCheck(CarbonInterface $date): void
    {
        $answers = BiosecurityChecklistItem::where('is_active', true)->get()->map(fn ($item) => ['item_id' => $item->id, 'passed' => mt_rand(1, 100) > 12, 'notes' => null])->all();
        $this->attempt('biosecurity checks', fn () => app(RecordBiosecurityCheck::class)($date->copy()->startOfDay(), $answers, ProductionUnit::firstWhere('code', 'PIG')->id));
    }

    private function visitors(CarbonInterface $date): void
    {
        $names = [['Dr. Ade Johnson', 'State Veterinary Services', 'Inspection'], ['Kunle Feeds driver', 'Greenfield Grains', 'Delivery'], ['Ibrahim Sule', 'Hilltop Farms', 'Buying pigs'], ['Mrs Okoro', 'City Meats', 'Meat collection']];
        [$name, $org, $purpose] = $names[mt_rand(0, 3)];
        $visit = $this->attempt('visitors', fn () => app(RecordVisitorArrival::class)([
            'visitor_name' => $name, 'organisation' => $org, 'purpose' => $purpose, 'health_declaration' => true, 'last_pig_contact_hours' => 72,
            'vehicle_registration' => 'LND-'.mt_rand(100, 999).'AB', 'arrived_at' => now()->subHour(),
        ], $this->staff['farm']));

        $visit && $this->attempt('visitor departures', fn () => app(RecordVisitorDeparture::class)($visit, now()));
    }

    // -------------------------------------------------------------- production

    /** Pigs in the batch now, or 0 once it is closed. */
    private function heads(ProductionBatch $batch): int
    {
        $batch->refresh();

        return $batch->isActive() ? $batch->headCount() : 0;
    }

    private function growthOf(ProductionBatch $batch): float
    {
        $g = $this->ref['growth'][$batch->id] ?? null;

        return $g ? round($g['w0'] + $g['placed']->diffInDays(now()) * 0.74, 2) : 30.0;
    }

    private function weighBatches(CarbonInterface $date): void
    {
        foreach ($this->pigBatches as $batch) {
            $batch->refresh();

            if (($count = $this->heads($batch)) < 1 || ! isset($this->ref['growth'][$batch->id])) {
                continue;
            }

            $this->attempt('batch weigh-ins', fn () => app(RecordBatchWeighIn::class)($batch, $date->copy()->startOfDay(), min(20, $count), number_format($this->growthOf($batch), 2, '.', '')));
        }
    }

    private function weeklyCosts(CarbonInterface $date): void
    {
        foreach ($this->pigBatches as $batch) {
            $batch->refresh();

            if (($count = $this->heads($batch)) > 0) {
                $this->attempt('batch costs', fn () => app(RecordProductionCost::class)($batch, $date->copy()->startOfDay(), ProductionCostCategory::Labour, 40000 * $count, 'Weekly labour allocation'));
            }
        }

        $batches = collect($this->pigBatches)->filter(fn ($b) => $this->heads($b) > 25);

        if ($batches->isNotEmpty() && mt_rand(1, 100) <= 55) {
            $batch = $batches->random();
            $this->attempt('batch mortality', fn () => app(RecordBatchMortality::class)($batch, mt_rand(1, 2), $date->copy()->startOfDay(), $this->lookup(L::MortalityCause, ['scours', 'respiratory', 'injury', 'unknown'][mt_rand(0, 3)])));
        }
    }

    // --------------------------------------------------------------- slaughter

    private function slaughter(CarbonInterface $date, int $day): void
    {
        $batch = collect($this->pigBatches)->first(fn ($b) => $b->stage?->code === 'finisher' && $this->heads($b) > 6 && $this->growthOf($b) >= 85);

        if (! $batch) {
            return;
        }

        $heads = min(8, $this->heads($batch));
        $live = number_format($heads * mt_rand(9600, 10400) / 100, 2, '.', '');
        $hot = number_format((float) $live * mt_rand(7400, 7800) / 10000, 2, '.', '');

        $this->attempt('slaughter', function () use ($date, $batch, $heads, $live, $hot) {
            $day = app(ManageSlaughterBatch::class)->schedule($date->copy()->startOfDay());
            $record = app(RecordSlaughterIntake::class)($day, $batch, $live, AnteMortemResult::Passed, null, $heads, $heads * 4800000, now()->subHour());
            $carcass = app(RecordSlaughter::class)($record, $hot, PostMortemResult::Passed, null, null, now());
            app(ManageSlaughterBatch::class)->complete($day->refresh());
            $this->ref['carcasses'][] = $carcass->id;
            $this->counts['pigs slaughtered'] = ($this->counts['pigs slaughtered'] ?? 0) + $heads;
        });
    }

    private function makeMeat(): void
    {
        $ids = $this->ref['carcasses'] ?? [];
        $this->ref['carcasses'] = [];

        if ($ids === []) {
            return;
        }

        $hot = (float) DB::table('carcasses')->whereIn('id', $ids)->sum('hot_weight_kg');
        $cuts = ['LEG' => 0.26, 'LOIN' => 0.17, 'SHOULDER' => 0.22, 'BELLY' => 0.14, 'RIBS' => 0.07, 'LIVER' => 0.02];
        $lines = collect($cuts)->map(fn ($share, $code) => ['meat_product_id' => MeatProduct::firstWhere('code', $code)->id, 'weight_kg' => number_format($hot * $share, 2, '.', '')])->values()->all();

        $this->attempt('meat production', fn () => app(ProduceMeat::class)($ids, $this->ref['loc']['COLD1'], now()->startOfDay(), $lines, number_format($hot * 0.04, 2, '.', ''), 250000));
    }

    // ------------------------------------------------------------------ sales

    private function sell(Customer $customer, array $lines, CarbonInterface $date, string $label): ?Invoice
    {
        return $this->attempt($label, function () use ($customer, $lines, $date) {
            // Customers without approved credit pay in advance (a deposit), as the credit rule requires.
            if ($customer->credit_status !== CreditStatus::Approved) {
                $expected = (int) round(collect($lines)->sum(fn ($l) => (float) ($l['quantity'] ?? 0) * (int) ($l['unit_price_minor'] ?? 0)));
                app(RecordCustomerPayment::class)($customer, $expected, ReceiptMethod::Cash, $date->copy()->startOfDay(), 'DEP-'.mt_rand(1000, 9999));
            }

            $order = app(CreateSalesOrder::class)($customer, $lines, $date->copy()->startOfDay());
            app(ConfirmSalesOrder::class)($order, $this->staff['farm']);
            $invoice = app(DispatchSalesOrder::class)($order->refresh(), $date->copy()->startOfDay(), 'DN-'.mt_rand(1000, 9999));
            $this->counts['sales orders'] = ($this->counts['sales orders'] ?? 0) + 1;

            $this->ref['open'][] = ['invoice' => $invoice, 'customer' => $customer, 'pay_on' => $date->copy()->addDays(mt_rand(3, max(4, $customer->payment_terms_days)) + mt_rand(0, 12))];

            return $invoice;
        });
    }

    private function sellMeat(CarbonInterface $date): void
    {
        $stock = app(GetStockLevels::class)()->filter(fn ($r) => $r->category === InventoryCategory::Meat->value ?? false);
        $lots = DB::table('meat_production_lines')->exists();

        if (! $lots) {
            return;
        }

        $buyers = collect($this->customers)->take(6)->shuffle()->take(2);

        foreach ($buyers as $customer) {
            $product = MeatProduct::whereIn('code', ['LEG', 'LOIN', 'SHOULDER', 'BELLY', 'RIBS'])->inRandomOrder()->first();
            $kg = (string) mt_rand(15, 45);
            $price = ['LEG' => 260000, 'LOIN' => 320000, 'SHOULDER' => 240000, 'BELLY' => 300000, 'RIBS' => 280000][$product->code];
            $this->sell($customer, [['kind' => 'meat', 'meat_product_id' => $product->id, 'inventory_location_id' => $this->ref['loc']['COLD1']->id, 'quantity' => $kg, 'unit_price_minor' => $price]], $date, 'meat sales');
        }
    }

    private function sellSemen(CarbonInterface $date): void
    {
        $batch = collect($this->semenStock)->first(fn ($st) => $st['left'] >= 6 && Carbon::parse($st['batch']->expiry_date)->greaterThan($date));

        if (! $batch) {
            return;
        }

        $buyer = $this->customers[mt_rand(2, 3)];
        $doses = mt_rand(4, min(10, $batch['left']));

        if ($this->sell($buyer, [['kind' => 'semen', 'semen_batch_id' => $batch['batch']->id, 'inventory_location_id' => $this->ref['loc']['SEMEN']->id, 'quantity' => $doses, 'unit_price_minor' => 1500000]], $date, 'semen sales')) {
            $this->useDoses($batch['batch']->id, $doses);
        }
    }

    private function sellPigs(CarbonInterface $date, int $day): void
    {
        if ($day % 14 !== 3) {
            return;
        }

        $batch = collect($this->pigBatches)->first(fn ($b) => $b->stage?->code === 'weaner' && $this->heads($b) > 15);

        if (! $batch) {
            return;
        }

        $heads = mt_rand(8, 14);
        $this->sell($this->customers[mt_rand(3, 6)], [['kind' => 'pig_batch', 'production_batch_id' => $batch->id, 'heads' => $heads, 'unit' => 'head', 'unit_price_minor' => 4500000]], $date, 'pig sales');
    }

    private function payInvoices(CarbonInterface $date): void
    {
        foreach ($this->ref['open'] ?? [] as $i => $row) {
            if ($row['pay_on']->greaterThan($date)) {
                continue;
            }

            unset($this->ref['open'][$i]);
            $invoice = $row['invoice']->refresh();
            $balance = (int) $invoice->total_minor - (int) ($invoice->paid_minor ?? 0);

            if ($balance <= 0 || $row['customer']->name === 'Sunrise Farmers Union') {
                continue;   // this customer pays slowly: their invoices go overdue on purpose
            }

            $amount = mt_rand(1, 100) <= 25 ? (int) round($balance * 0.6, -2) : $balance;
            $this->attempt('customer receipts', fn () => app(RecordCustomerPayment::class)($row['customer'], $amount, [ReceiptMethod::BankTransfer, ReceiptMethod::Cash, ReceiptMethod::Pos][mt_rand(0, 2)], $date->copy()->startOfDay(), 'RCPT-'.mt_rand(10000, 99999)));
        }
    }

    // ---------------------------------------------------------------- finance

    private function expenses(CarbonInterface $date): void
    {
        $pick = [
            ['5400', 'ADM', 'Diesel for generator', 18500000, 'Total Energies'], ['5500', 'GRW', 'Pen repairs and repainting', 9500000, 'Local contractor'],
            ['5200', 'BRD', 'Veterinary consumables', 12500000, 'Savannah Veterinary Supplies'], ['5900', 'ADM', 'Stationery and airtime', 1850000, 'Office supplies'],
            ['5400', 'FDM', 'Electricity — feed mill', 22000000, 'Ibadan DisCo'],
        ];
        [$acc, $cc, $what, $amount, $payee] = $pick[mt_rand(0, count($pick) - 1)];
        $this->attempt('expenses', fn () => app(RecordExpense::class)($date->copy()->startOfDay(), $this->ref['acc'][$acc], $this->ref['cc'][$cc], $amount + mt_rand(-500000, 1500000), $this->ref['acc']['1010'], $payee, 'EXP-'.mt_rand(1000, 9999), $what));
    }

    private function payroll(CarbonInterface $date): void
    {
        $this->attempt('payroll', fn () => app(RecordExpense::class)($date->copy()->startOfDay(), $this->ref['acc']['5300'], $this->ref['cc']['ADM'], 420000000, $this->ref['acc']['1010'], 'Staff payroll', 'PAY-'.$date->format('Ym'), 'Monthly salaries'));
        $this->attempt('payroll journal', function () use ($date) {
            $entry = app(PostManualJournal::class)($date->copy()->startOfDay(), 'Accrue pension contribution', [
                ['account_id' => $this->ref['acc']['5300'], 'debit_minor' => 21000000, 'credit_minor' => 0],
                ['account_id' => $this->ref['acc']['2000'], 'debit_minor' => 0, 'credit_minor' => 21000000],
            ]);
            $entry->status->value === 'pending' && app(DecideJournal::class)->approve($entry, $this->staff['gm']);
        });
    }

    private function monthStart(CarbonInterface $date): void
    {
        $this->attempt('rent', fn () => app(RecordExpense::class)($date->copy()->startOfDay(), $this->ref['acc']['5900'], $this->ref['cc']['ADM'], 85000000, $this->ref['acc']['1010'], 'Landlord', 'RENT-'.$date->format('Ym'), 'Office and stores rent'));
    }

    // -------------------------------------------------------------- inventory

    private function stockCount(CarbonInterface $date): void
    {
        $this->attempt('stock counts', function () use ($date) {
            $count = app(StartStockCount::class)($this->ref['loc']['RAW'], $date->copy()->startOfDay(), 'Monthly raw material count');

            foreach ($count->lines as $line) {
                $short = bccomp((string) $line->system_quantity, '60', 3) > 0 && mt_rand(1, 100) <= 60 ? (string) mt_rand(5, 40) : '0';
                app(RecordCountLine::class)($count, $line->inventory_item_id, $line->inventory_batch_id, bcsub((string) $line->system_quantity, $short, 3), $short === '0' ? null : 'Spillage and moisture loss');
            }

            app(SubmitStockCount::class)($count);
            app(ApproveStockCount::class)($count->refresh(), $this->staff['farm']);
        });
    }

    // ----------------------------------------------------------- odds and ends

    private function incidentals(CarbonInterface $date, int $day): void
    {
        $sowCategoryId = $this->lookup(L::AnimalCategory, 'sow');

        if ($day === 20) {
            $this->attempt('new gilts', function () use ($date) {
                for ($i = 0; $i < 3; $i++) {
                    $gilt = app(RegisterAnimal::class)([
                        'sex' => 'female', 'category_id' => $this->lookup(L::AnimalCategory, 'gilt'), 'source' => 'purchased', 'breed_id' => Breed::firstWhere('code', 'LR')->id,
                        'birth_date' => $date->copy()->subMonths(10)->toDateString(), 'acquired_on' => $date->toDateString(), 'pen_id' => $this->pen('QH', $i),
                    ]);
                    $record = app(StartQuarantine::class)($gilt, QuarantineType::Quarantine, $date->copy()->startOfDay(), 'New stock from outside');
                    $this->later($date->copy()->addDays(14), function () use ($record, $gilt) {
                        $this->attempt('quarantine release', fn () => app(ReleaseQuarantine::class)($record->refresh(), now()->startOfDay(), 'Healthy after 14 days'));
                        $this->sows[] = ['animal' => $gilt, 'state' => 'open', 'service' => null, 'litter' => null, 'next' => now()->addDays(10)];
                    });
                }
            });
        }

        if ($day === 55 && count($this->sows) > 5) {
            $victim = $this->sows[array_key_last($this->sows)];
            $this->attempt('mortality', fn () => app(RecordMortality::class)($victim['animal'], $date->copy()->startOfDay(), $this->lookup(L::MortalityCause, 'disease_other'), Disease::firstWhere('code', 'PNEUM')->id, 'Found dead at morning round'));
            $this->sows = array_values(array_filter($this->sows, fn ($s) => $s['animal']->id !== $victim['animal']->id));
        }

        if ($day === 70) {
            $sow = collect($this->sows)->first(fn ($s) => $s['state'] === 'open');

            if ($sow) {
                $this->attempt('culling', fn () => app(RecordCulling::class)($sow['animal'], $date->copy()->startOfDay(), $this->lookup(L::CullReason, 'reproductive_failure'), '212.50', CullHealthStatus::Healthy, DisposalType::Sold, 38500000, 'Repeat breeder'));
                $this->sows = array_values(array_filter($this->sows, fn ($s) => $s['animal']->id !== $sow['animal']->id));
            }
        }

        if ($day % 30 === 5) {
            foreach (collect($this->sows)->take(6) as $row) {
                $this->attempt('animal weights', fn () => app(RecordWeight::class)($row['animal'], (string) mt_rand(18000, 24500) / 100, $date->copy(), 'scale'));
            }
        }

        if ($day === 12) {
            $this->attempt('purchase requests', function () {
                $request = app(CreatePurchaseRequest::class)([['inventory_item_id' => $this->ref['item']['soya']->id, 'quantity' => '3000', 'estimated_unit_cost_minor' => 90000]], now()->addDays(7), 'Soya stock running low', $this->staff['store']);
                app(DecidePurchaseRequest::class)->submit($request);
                app(DecidePurchaseRequest::class)->approve($request, $this->staff['farm']);
            });
        }
    }

    private function enquiry(CarbonInterface $date): void
    {
        $samples = [
            ['pigs', 'Femi Adeyemi', 'Please quote for 20 weaner pigs for delivery to Abeokuta next week.', '0803'.mt_rand(1000000, 9999999)],
            ['semen', 'Okon Breeders', 'We want Duroc semen, 30 doses a month. What are your terms?', '0805'.mt_rand(1000000, 9999999)],
            ['meat', 'Grace Kitchen', 'Do you supply pork belly to restaurants and what is the minimum order?', '0809'.mt_rand(1000000, 9999999)],
        ];
        [$kind, $name, $message, $phone] = $samples[mt_rand(0, 2)];
        $this->attempt('website enquiries', fn () => app(SubmitEnquiry::class)(['kind' => $kind, 'name' => $name, 'message' => $message.' ('.$date->format('d M').')', 'phone' => $phone]));
    }

    private function tasks(CarbonInterface $date): void
    {
        if ($date->dayOfWeekIso === 2) {
            $this->attempt('manual tasks', function () use ($date) {
                app(CreateTask::class)('Calibrate the farm scales', $date->copy()->addDays(3)->startOfDay(), TaskCategory::Inventory, TaskPriority::Normal, 'Use the 20 kg test weight and record the readings.', $this->staff['store']);
                app(CreateTask::class)('Service the feed mill mixer', $date->copy()->addDays(5)->startOfDay(), TaskCategory::Production, TaskPriority::High, null, $this->staff['mill']);
            });
        }

        // Staff work through what is due: most things done on time, a few left to show up as overdue.
        Task::query()->whereIn('status', ['open', 'in_progress'])->whereDate('due_on', '<=', $date->toDateString())->orderBy('id')->limit(40)->get()->each(function (Task $task) {
            if (mt_rand(1, 100) <= 82) {
                $worker = collect($this->staff)->first(fn ($u) => app(AdvanceTask::class)->mayWork($task, $u));

                if (! $worker) {
                    return;
                }

                $this->attempt('task work', function () use ($task, $worker) {
                    app(AdvanceTask::class)->start($task, $worker);
                    app(AdvanceTask::class)->complete($task->refresh(), $worker, 'Done');
                });
            }
        });
    }

    /** The last days: leave some work waiting so the approval inbox, alerts and reports have something to show. */
    private function leaveSomeWaiting(): void
    {
        Carbon::setTestNow(now()->setTime(17, 0));

        $this->attempt('waiting purchase order', function () {
            $order = app(CreatePurchaseOrder::class)($this->ref['supplier']['EQP'], [['inventory_item_id' => $this->ref['item']['maize']->id, 'quantity' => '6000', 'unit_cost_minor' => 34000]], now()->startOfDay());
            app(DecidePurchaseOrder::class)->submit($order);
        });

        $this->attempt('waiting stock adjustment', fn () => app(RequestStockAdjustment::class)($this->ref['item']['soya'], $this->ref['loc']['RAW'], null, '-120', 'Bags found wet after roof leak', $this->staff['store']));
        $this->attempt('waiting sales order', fn () => app(CreateSalesOrder::class)($this->customers[0], [['kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => $this->ref['loc']['COLD1']->id, 'quantity' => '10', 'unit_price_minor' => 260000]], now()->startOfDay()));
    }

    // ------------------------------------------------------------- bookkeeping

    private function attempt(string $what, callable $do): mixed
    {
        try {
            $result = $do();
            $this->counts[$what] = ($this->counts[$what] ?? 0) + 1;

            return $result;
        } catch (Throwable $e) {
            $this->failures[$what][] = Str::limit($e->getMessage(), 140).' ['.class_basename($e).' '.basename($e->getFile()).':'.$e->getLine().']';

            return null;
        }
    }

    private function report(): void
    {
        if (! $this->command) {
            return;
        }

        ksort($this->counts);
        $this->command->info('Demo data loaded for the '.self::DAYS.' days up to today.');
        $this->command->table(['What', 'Done'], collect($this->counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        if ($this->failures !== []) {
            $this->command->warn('Some steps were refused by the business rules (this is normal for a few; check any with many):');
            $this->command->table(['Step', 'Refused', 'First reason'], collect($this->failures)->map(fn ($list, $k) => [$k, count($list), $list[0]])->values()->all());
        }
    }
}
