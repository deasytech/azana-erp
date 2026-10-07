<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\Procurement\Models\SupplierPayment;
use App\Domain\Semen\Models\SemenBatch;
use App\Enums\ApprovalStatus;
use App\Enums\BudgetStatus;
use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\SemenBatchStatus;
use App\Enums\StockCountStatus;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Resources\SemenBatches\SemenBatchResource;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Filament\Resources\StockCounts\StockCountResource;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything waiting for a decision that this user may make, from every module that asks for approval: manual journals, large supplier
 * payments, purchase requests and orders, stock adjustments and counts, semen batches awaiting release, and draft budgets.
 * Only modules where the user holds the approve permission are listed. "mine" marks what the user raised themselves (they may not
 * decide it when the module requires a different approver).
 */
class GetPendingApprovals
{
    /** @return Collection<int, array{type: string, module: string, reference: string, detail: string, raised_by: ?string, raised_at: mixed, mine: bool, url: string}> */
    public function __invoke(User $user): Collection
    {
        if (! $user->is_active) {
            return collect();
        }

        // Each group is only read when the user may approve in its module.
        $groups = [
            'finance' => fn () => collect()
                ->concat(JournalEntry::with('createdBy')->where('status', JournalStatus::Pending)->get()->map(fn ($j) => $this->item('Manual journal', 'finance', $j->number, $j->description, $j->createdBy, $j->created_at, $j->created_by, $user, JournalEntryResource::getUrl('view', ['record' => $j]))))
                ->concat(Budget::with('createdBy')->where('status', BudgetStatus::Draft)->whereHas('lines')->get()->map(fn ($b) => $this->item('Budget', 'finance', "{$b->name} ({$b->fiscal_year})", '', $b->createdBy, $b->created_at, $b->created_by, $user, BudgetResource::getUrl('index')))),
            'procurement' => fn () => collect()
                ->concat(SupplierPayment::with(['invoice', 'createdBy'])->where('status', PaymentStatus::PendingApproval)->get()->map(fn ($p) => $this->item('Supplier payment', 'procurement', $p->number, "Invoice {$p->invoice->number}", $p->createdBy, $p->created_at, $p->created_by, $user, SupplierPaymentResource::getUrl('index'))))
                ->concat(PurchaseRequest::with('requestedBy')->where('status', PurchaseRequestStatus::Submitted)->get()->map(fn ($r) => $this->item('Purchase request', 'procurement', $r->number, (string) $r->notes, $r->requestedBy, $r->submitted_at ?? $r->created_at, $r->requested_by, $user, PurchaseRequestResource::getUrl('view', ['record' => $r]))))
                ->concat(PurchaseOrder::with(['supplier', 'createdBy'])->where('status', PurchaseOrderStatus::PendingApproval)->get()->map(fn ($o) => $this->item('Purchase order', 'procurement', $o->number, $o->supplier->name, $o->createdBy, $o->created_at, $o->created_by, $user, PurchaseOrderResource::getUrl('view', ['record' => $o])))),
            'inventory' => fn () => collect()
                ->concat(StockAdjustment::with(['item', 'requestedBy'])->where('status', ApprovalStatus::Pending)->get()->map(fn ($a) => $this->item('Stock adjustment', 'inventory', $a->number, "{$a->item->name}: {$a->quantity}", $a->requestedBy, $a->created_at, $a->requested_by, $user, StockAdjustmentResource::getUrl('index'))))
                ->concat(StockCount::with('submittedBy')->where('status', StockCountStatus::Submitted)->get()->map(fn ($c) => $this->item('Stock count', 'inventory', $c->number, "Counted {$c->counted_on->format('d M Y')}", $c->submittedBy, $c->submitted_at ?? $c->created_at, $c->submitted_by, $user, StockCountResource::getUrl('view', ['record' => $c])))),
            'semen' => fn () => SemenBatch::with('boar')->where('status', SemenBatchStatus::Passed)->get()->map(fn ($b) => $this->item('Semen batch release', 'semen', $b->number, "Boar {$b->boar->animal_number}", null, $b->processed_at ?? $b->created_at, null, $user, SemenBatchResource::getUrl('view', ['record' => $b]))),
        ];

        return collect($groups)->filter(fn ($load, $module) => $user->can("{$module}.approve"))->flatMap(fn ($load) => $load())->sortBy('raised_at')->values();
    }

    /** @return array<string, mixed> */
    private function item(string $type, string $module, string $reference, string $detail, ?User $raisedBy, mixed $raisedAt, ?int $raisedById, User $user, string $url): array
    {
        return ['type' => $type, 'module' => $module, 'reference' => $reference, 'detail' => $detail, 'raised_by' => $raisedBy?->name, 'raised_at' => $raisedAt, 'mine' => $raisedById !== null && $raisedById === $user->id, 'url' => $url];
    }
}
