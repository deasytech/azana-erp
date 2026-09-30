<?php

namespace App\Domain\Biosecurity\Actions;

use App\Domain\Biosecurity\Models\BiosecurityVisit;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Logs a visitor's arrival. A visitor without a health declaration, or with less pig-free time than the
 * farm requires, may only enter with the approval of someone allowed to approve biosecurity exceptions.
 *
 * $data keys: visitor_name, purpose, organisation, phone, vehicle_registration, arrived_at (CarbonInterface),
 * last_pig_contact_hours, health_declaration, areas_visited, host_id, host_name, notes.
 */
class RecordVisitorArrival
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, ?User $approver = null, ?User $actor = null): BiosecurityVisit
    {
        $arrivedAt = $data['arrived_at'] ?? now();

        if (trim((string) ($data['visitor_name'] ?? '')) === '' || trim((string) ($data['purpose'] ?? '')) === '') {
            throw new DomainException('Record the visitor\'s name and the purpose of the visit.', 'visit_incomplete');
        }

        if ($arrivedAt instanceof CarbonInterface && $arrivedAt->gt(now()->addMinutes(5))) {
            throw new DomainException('An arrival cannot be dated in the future.', 'arrival_future');
        }

        $needsApproval = $this->needsApproval($data);

        if ($needsApproval && ! $approver?->can('biosecurity.approve')) {
            throw new DomainException('This visitor has not met the biosecurity entry conditions and needs approval from a manager.', 'visitor_needs_approval');
        }

        return BiosecurityVisit::create([
            ...collect($data)->only(['visitor_name', 'organisation', 'phone', 'vehicle_registration', 'purpose', 'last_pig_contact_hours', 'areas_visited', 'host_id', 'host_name', 'notes'])->all(),
            'arrived_at' => $arrivedAt,
            'health_declaration' => (bool) ($data['health_declaration'] ?? false),
            'approved_by' => $needsApproval ? $approver->id : null,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function needsApproval(array $data): bool
    {
        $hours = $data['last_pig_contact_hours'] ?? null;

        return ! ($data['health_declaration'] ?? false)
            || $hours === null
            || $hours < (int) $this->settings->get('biosecurity.min_pig_contact_free_hours');
    }
}
