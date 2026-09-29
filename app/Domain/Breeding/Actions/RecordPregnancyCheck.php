<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Breeding\Concerns\FindsSameHeatServices;
use App\Domain\Breeding\Events\PregnancyConfirmed;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\PregnancyCheckMethod;
use App\Enums\PregnancyCheckResult;
use App\Enums\ServiceOutcome;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a pregnancy check. The result applies to the checked service and to the sow's other open
 * services within the same-heat window (double mating), because the check cannot tell them apart.
 */
class RecordPregnancyCheck
{
    use FindsSameHeatServices;

    public function __construct(private readonly ResolveSettings $settings) {}

    public function __invoke(
        BreedingService $service,
        PregnancyCheckResult $result,
        CarbonInterface $checkedOn,
        PregnancyCheckMethod $method = PregnancyCheckMethod::Ultrasound,
        ?string $notes = null,
        ?User $actor = null,
    ): PregnancyCheck {
        return DB::transaction(function () use ($service, $result, $checkedOn, $method, $notes, $actor) {
            $service = BreedingService::lockForUpdate()->findOrFail($service->id);

            if (! $service->outcome->isOpen()) {
                throw new DomainException("This service is already {$service->outcome->label()}.", 'service_closed');
            }

            if ($checkedOn->lt($service->serviced_on) || $checkedOn->isFuture()) {
                throw new DomainException('A pregnancy check must fall between the service date and today.', 'check_date');
            }

            $check = $service->checks()->create([
                'checked_on' => $checkedOn,
                'result' => $result,
                'method' => $method,
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
            ]);

            $outcome = $result === PregnancyCheckResult::Positive ? ServiceOutcome::Pregnant : ServiceOutcome::NotPregnant;
            $this->sameHeatServices($service)->each(fn (BreedingService $s) => $s->forceFill(['outcome' => $outcome, 'outcome_on' => $checkedOn])->save());

            if ($result === PregnancyCheckResult::Positive) {
                PregnancyConfirmed::dispatch($check);
            }

            return $check;
        });
    }
}
