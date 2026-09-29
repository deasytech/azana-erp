<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Breeding\Concerns\FindsSameHeatServices;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Actions\RecordAudit;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ServiceOutcome;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Closes a pregnancy that ended without farrowing. The sow returns to open. */
class RecordAbortion
{
    use FindsSameHeatServices;

    public function __construct(private readonly ResolveSettings $settings, private readonly RecordAudit $audit) {}

    public function __invoke(BreedingService $service, CarbonInterface $occurredOn, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('A reason is required to record an abortion.', 'reason_required');
        }

        DB::transaction(function () use ($service, $occurredOn, $reason) {
            $service = BreedingService::lockForUpdate()->findOrFail($service->id);

            if (! $service->outcome->isOpen()) {
                throw new DomainException("This service is already {$service->outcome->label()}.", 'service_closed');
            }

            if ($occurredOn->lt($service->serviced_on) || $occurredOn->isFuture()) {
                throw new DomainException('An abortion must fall between the service date and today.', 'abortion_date');
            }

            $this->sameHeatServices($service)->each(function (BreedingService $s) use ($occurredOn, $reason) {
                $s->forceFill(['outcome' => ServiceOutcome::Aborted, 'outcome_on' => $occurredOn])->save();
                ($this->audit)('abortion_recorded', $s, null, ['occurred_on' => $occurredOn->toDateString()], trim($reason));
            });
        });
    }
}
