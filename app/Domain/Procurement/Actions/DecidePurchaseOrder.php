<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Procurement\Concerns\ChecksProcurementApproval;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PurchaseOrderStatus as Status;
use App\Enums\PurchaseRequestStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The approval steps of a purchase order. Submitting sends it for approval, unless its total is within the
 * configured limit (`procurement.po_approval_threshold_minor`), in which case it is approved on the spot.
 */
class DecidePurchaseOrder
{
    use ChecksProcurementApproval;

    public function __construct(private readonly ResolveSettings $settings) {}

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);
            $this->assertIn($order, [Status::Draft], 'Only a draft order can be submitted.');

            $withinLimit = $order->total_minor <= (int) $this->settings->get('procurement.po_approval_threshold_minor');
            $order->update(['submitted_at' => now()] + ($withinLimit
                ? ['status' => Status::Approved, 'decided_at' => now(), 'decision_notes' => 'Within the approval limit.']
                : ['status' => Status::PendingApproval]));

            return $order;
        });
    }

    public function approve(PurchaseOrder $order, User $approver, ?string $notes = null): PurchaseOrder
    {
        return $this->decide($order, $approver, Status::Approved, $notes);
    }

    public function reject(PurchaseOrder $order, User $approver, string $reason): PurchaseOrder
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reject an order.', 'reason_required');
        }

        return DB::transaction(function () use ($order, $approver, $reason) {
            $order = $this->decide($order, $approver, Status::Rejected, trim($reason));
            $this->releaseRequest($order);

            return $order;
        });
    }

    /** Cancels an order before anything has been received (a partly received order cannot be); its request can be ordered again. */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);
            $this->assertIn($order, [Status::Draft, Status::PendingApproval, Status::Approved], 'This order can no longer be cancelled.');

            $order->update(['status' => Status::Cancelled]);
            $this->releaseRequest($order);

            return $order;
        });
    }

    private function decide(PurchaseOrder $order, User $approver, Status $to, ?string $notes): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $approver, $to, $notes) {
            $order = $this->lock($order);
            $this->assertIn($order, [Status::PendingApproval], 'Only an order waiting for approval can be decided.');
            $this->assertMayDecide($approver, $order->created_by, 'purchase order');
            $order->update(['status' => $to, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $order;
        });
    }

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::lockForUpdate()->findOrFail($order->id);
    }

    /** @param list<Status> $allowed */
    private function assertIn(PurchaseOrder $order, array $allowed, string $message): void
    {
        in_array($order->status, $allowed, true) || throw new DomainException($message, 'order_state');
    }

    private function releaseRequest(PurchaseOrder $order): void
    {
        $order->request?->update(['status' => PurchaseRequestStatus::Approved]);
    }
}
