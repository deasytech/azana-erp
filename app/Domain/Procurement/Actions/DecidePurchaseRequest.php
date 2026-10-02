<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Concerns\ChecksProcurementApproval;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PurchaseRequestStatus as Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The steps of a purchase request: submit, approve, reject and cancel. */
class DecidePurchaseRequest
{
    use ChecksProcurementApproval;

    public function submit(PurchaseRequest $request): PurchaseRequest
    {
        return $this->move($request, [Status::Draft], Status::Submitted, ['submitted_at' => now()], 'Only a draft request can be submitted.');
    }

    public function approve(PurchaseRequest $request, User $approver, ?string $notes = null): PurchaseRequest
    {
        return $this->decide($request, $approver, Status::Approved, $notes);
    }

    public function reject(PurchaseRequest $request, User $approver, string $reason): PurchaseRequest
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to reject a request.', 'reason_required');
        }

        return $this->decide($request, $approver, Status::Rejected, trim($reason));
    }

    public function cancel(PurchaseRequest $request): PurchaseRequest
    {
        return $this->move($request, [Status::Draft, Status::Submitted, Status::Approved], Status::Cancelled, [], 'This request can no longer be cancelled.');
    }

    private function decide(PurchaseRequest $request, User $approver, Status $to, ?string $notes): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $approver, $to, $notes) {
            $request = PurchaseRequest::lockForUpdate()->findOrFail($request->id);
            $this->assertIn($request, [Status::Submitted], 'Only a submitted request can be decided.');
            $this->assertMayDecide($approver, $request->requested_by, 'purchase request');
            $request->update(['status' => $to, 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);

            return $request;
        });
    }

    /** @param list<Status> $from */
    private function move(PurchaseRequest $request, array $from, Status $to, array $extra, string $message): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $from, $to, $extra, $message) {
            $request = PurchaseRequest::lockForUpdate()->findOrFail($request->id);
            $this->assertIn($request, $from, $message);

            $request->update(['status' => $to] + $extra);

            return $request;
        });
    }

    /** @param list<Status> $allowed */
    private function assertIn(PurchaseRequest $request, array $allowed, string $message): void
    {
        in_array($request->status, $allowed, true) || throw new DomainException($message, 'request_state');
    }
}
