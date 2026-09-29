<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Events\BreedingServiceRecorded;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ReproductiveStatus;
use App\Enums\ServiceMethod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a natural or AI service and snapshots the expected pregnancy check, farrowing,
 * weaning, next heat and next service dates from the farm's breeding settings.
 */
class RecordService
{
    public function __construct(
        private readonly ResolveSettings $settings,
        private readonly GetSowStatus $sowStatus,
    ) {}

    public function __invoke(
        Animal $sow,
        ServiceMethod $method,
        CarbonInterface $servicedOn,
        ?int $boarId = null,
        ?string $semenSource = null,
        ?User $technician = null,
        ?string $technicianName = null,
        ?string $notes = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): BreedingService {
        return DB::transaction(function () use ($sow, $method, $servicedOn, $boarId, $semenSource, $technician, $technicianName, $notes, $actor, $idempotencyKey) {
            $sow = Animal::lockForUpdate()->with('category')->findOrFail($sow->id);

            if ($idempotencyKey && ($existing = BreedingService::firstWhere('idempotency_key', $idempotencyKey))) {
                if ($existing->sow_id !== $sow->id) {
                    throw new DomainException('This idempotency key was already used for a different sow.', 'idempotency_conflict');
                }

                return $existing;
            }

            $semenSource = $semenSource !== null && trim($semenSource) !== '' ? trim($semenSource) : null;
            $this->assertSow($sow, $servicedOn);
            $this->assertSire($method, $boarId, $semenSource);

            $service = BreedingService::create([
                'sow_id' => $sow->id,
                'boar_id' => $boarId,
                'method' => $method,
                'serviced_on' => $servicedOn,
                'semen_source' => $semenSource,
                'technician_id' => $technician?->getKey(),
                'technician_name' => $technicianName,
                'notes' => $notes,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $idempotencyKey,
                ...$this->expectedDates($servicedOn),
            ]);

            BreedingServiceRecorded::dispatch($service);

            return $service;
        });
    }

    /** @return array<string, CarbonInterface> */
    private function expectedDates(CarbonInterface $servicedOn): array
    {
        $farrowing = $servicedOn->copy()->addDays((int) $this->settings->get('breeding.gestation_days'));
        $weaning = $farrowing->copy()->addDays((int) $this->settings->get('breeding.weaning_age_days'));

        return [
            'expected_pregnancy_check_on' => $servicedOn->copy()->addDays((int) $this->settings->get('breeding.pregnancy_check_days')),
            'expected_farrowing_on' => $farrowing,
            'expected_weaning_on' => $weaning,
            'expected_next_heat_on' => $servicedOn->copy()->addDays((int) $this->settings->get('breeding.heat_cycle_days')),
            'expected_next_service_on' => $weaning->copy()->addDays((int) $this->settings->get('breeding.wean_to_service_days')),
        ];
    }

    private function assertSow(Animal $sow, CarbonInterface $servicedOn): void
    {
        if (! $sow->isBreedingFemale() || ! $sow->isActive()) {
            throw new DomainException('Only an active sow or gilt can be served.', 'not_breeding_female');
        }

        if ($servicedOn->isFuture()) {
            throw new DomainException('A service cannot be dated in the future.', 'service_future');
        }

        $status = ($this->sowStatus)($sow);

        if ($status === ReproductiveStatus::Pregnant) {
            throw new DomainException("{$sow->animal_number} is confirmed pregnant and cannot be served.", 'sow_pregnant');
        }

        if ($status === ReproductiveStatus::Lactating) {
            throw new DomainException("{$sow->animal_number} still has an unweaned litter.", 'sow_lactating');
        }

        $latest = BreedingService::where('sow_id', $sow->id)->max('serviced_on');

        if ($latest && $servicedOn->lt($latest)) {
            throw new DomainException('This is earlier than the sow\'s latest recorded service.', 'service_out_of_order');
        }

        $this->assertOldEnough($sow, $servicedOn);
    }

    private function assertOldEnough(Animal $sow, CarbonInterface $servicedOn): void
    {
        $minimum = (int) $this->settings->get('breeding.min_first_service_age_days');

        if (! $sow->birth_date || BreedingService::where('sow_id', $sow->id)->exists()) {
            return;
        }

        if ($sow->birth_date->diffInDays($servicedOn) < $minimum) {
            throw new DomainException("{$sow->animal_number} is younger than the minimum first-service age of {$minimum} days.", 'sow_too_young');
        }
    }

    private function assertSire(ServiceMethod $method, ?int $boarId, ?string $semenSource): void
    {
        if ($boarId !== null) {
            $boar = Animal::with('category')->find($boarId);

            if (! $boar?->isBoar() || ! $boar->isActive()) {
                throw new DomainException('The boar must be an active boar.', 'invalid_boar');
            }

            return;
        }

        if ($method === ServiceMethod::Natural) {
            throw new DomainException('Natural mating needs the boar that served the sow.', 'boar_required');
        }

        if ($semenSource === null) {
            throw new DomainException('Record the boar or the semen source for an AI service.', 'semen_source_required');
        }
    }
}
