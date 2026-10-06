<?php

namespace App\Providers;

use App\Domain\Animal\Models as A;
use App\Domain\Biosecurity\Models as S;
use App\Domain\Breeding\Models as B;
use App\Domain\Farm\Models as M;
use App\Domain\Feed\Models as FD;
use App\Domain\Health\Models as H;
use App\Domain\Inventory\Models as I;
use App\Domain\Litter\Models as L;
use App\Domain\Procurement\Models as PC;
use App\Domain\Production\Models as PR;
use App\Domain\Sales\Models as SA;
use App\Domain\Semen\Models as SM;
use App\Domain\Supplier\Models as SP;
use App\Enums\Module;
use App\Models\User;
use App\Policies\AnimalPhotoPolicy;
use App\Policies\AnimalPolicy;
use App\Policies\BiosecurityMasterPolicy;
use App\Policies\BiosecurityPolicy;
use App\Policies\BreedingPolicy;
use App\Policies\FarmStructurePolicy;
use App\Policies\FeedMillMasterPolicy;
use App\Policies\FeedMillPolicy;
use App\Policies\HealthMasterPolicy;
use App\Policies\HealthPolicy;
use App\Policies\InventoryMasterPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProcurementMasterPolicy;
use App\Policies\ProcurementPolicy;
use App\Policies\ProductionMasterPolicy;
use App\Policies\ProductionPolicy;
use App\Policies\SalesMasterPolicy;
use App\Policies\SalesPolicy;
use App\Policies\SemenPolicy;
use App\Policies\SettingsPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner/Director implicitly holds every "{module}.{action}" permission. Only permission
        // checks are bypassed: policy rules (no user deletion, read-only audit trails) still apply.
        foreach ([M\Farm::class, M\ProductionUnit::class, M\Building::class, M\Room::class, M\Pen::class, M\Location::class] as $model) {
            Gate::policy($model, FarmStructurePolicy::class);
        }
        foreach ([M\Breed::class, M\GeneticLine::class, M\UnitOfMeasure::class, M\LookupValue::class] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }
        foreach ([A\Animal::class, A\AnimalIdentifier::class, A\AnimalMovement::class, A\WeightRecord::class, A\AnimalStatusHistory::class, A\AnimalParentage::class] as $model) {
            Gate::policy($model, AnimalPolicy::class);
        }
        foreach ([B\HeatEvent::class, B\BreedingService::class, B\PregnancyCheck::class, B\Farrowing::class, L\Litter::class, L\Piglet::class, L\LitterLoss::class, L\WeaningRecord::class] as $model) {
            Gate::policy($model, BreedingPolicy::class);
        }
        foreach ([H\HealthEvent::class, H\Treatment::class, H\Vaccination::class, H\WithdrawalPeriod::class, H\VeterinaryVisit::class, H\LaboratoryResult::class, H\QuarantineRecord::class, H\MortalityRecord::class, H\CullingRecord::class] as $model) {
            Gate::policy($model, HealthPolicy::class);
        }
        foreach ([H\Disease::class, H\Medicine::class, H\MedicineBatch::class, H\VaccinationSchedule::class] as $model) {
            Gate::policy($model, HealthMasterPolicy::class);
        }
        foreach ([S\BiosecurityVisit::class, S\BiosecurityCheck::class, S\BiosecurityCheckItem::class] as $model) {
            Gate::policy($model, BiosecurityPolicy::class);
        }
        foreach ([PR\ProductionBatch::class, PR\ProductionBatchEvent::class, PR\ProductionBatchAnimal::class, PR\BatchWeighIn::class, PR\ProductionCost::class, FD\FeedConsumptionRecord::class] as $model) {
            Gate::policy($model, ProductionPolicy::class);
        }
        Gate::policy(FD\FeedType::class, ProductionMasterPolicy::class);
        Gate::policy(FD\FeedFormula::class, FeedMillMasterPolicy::class);
        foreach ([SA\SalesOrder::class, SA\SalesOrderLine::class, SA\StockReservation::class, SA\Invoice::class, SA\InvoiceLine::class, SA\Payment::class, SA\PaymentAllocation::class] as $model) {
            Gate::policy($model, SalesPolicy::class);
        }
        Gate::policy(SA\Customer::class, SalesMasterPolicy::class);
        foreach ([SM\SemenBoar::class, SM\SemenCollection::class, SM\SemenBatch::class, SM\SemenQcRecord::class] as $model) {
            Gate::policy($model, SemenPolicy::class);
        }
        foreach ([FD\FeedFormulaItem::class, FD\FeedProductionOrder::class, FD\FeedProductionOrderLine::class, FD\FeedProductionBatch::class] as $model) {
            Gate::policy($model, FeedMillPolicy::class);
        }
        foreach ([I\InventoryTransaction::class, I\StockCount::class, I\StockCountLine::class, I\StockAdjustment::class] as $model) {
            Gate::policy($model, InventoryPolicy::class);
        }
        foreach ([I\InventoryItem::class, I\InventoryLocation::class, I\InventoryBatch::class] as $model) {
            Gate::policy($model, InventoryMasterPolicy::class);
        }
        foreach ([PC\PurchaseRequest::class, PC\PurchaseRequestLine::class, PC\PurchaseOrder::class, PC\PurchaseOrderLine::class, PC\GoodsReceipt::class, PC\GoodsReceiptLine::class, PC\SupplierInvoice::class, PC\SupplierPayment::class] as $model) {
            Gate::policy($model, ProcurementPolicy::class);
        }
        Gate::policy(SP\Supplier::class, ProcurementMasterPolicy::class);
        Gate::policy(S\BiosecurityChecklistItem::class, BiosecurityMasterPolicy::class);
        Gate::policy(A\AnimalPhoto::class, AnimalPhotoPolicy::class);
        Gate::policy(M\FarmSetting::class, SettingsPolicy::class);
        Gate::policy(M\PriceList::class, PriceListPolicy::class);
        Gate::policy(M\PriceListItem::class, PriceListPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            $module = strstr($ability, '.', true);

            return $module !== false && Module::tryFrom($module) && $user->isOwner() ? true : null;
        });

        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

    }
}
