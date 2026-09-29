<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Concerns\FindsSameHeatServices;
use App\Domain\Breeding\Events\FarrowingRecorded;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Breeding\Models\Farrowing;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\Litter\Models\Litter;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\LitterStatus;
use App\Enums\LookupCategory;
use App\Enums\ServiceOutcome;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a farrowing and creates its litter automatically. A gilt becomes a sow at her first farrowing.
 *
 * $data keys: farrowed_on (CarbonInterface), total_born, born_alive, stillborn, mummified,
 * total_birth_weight_kg, assisted, notes, breeding_service_id, idempotency_key.
 */
class RecordFarrowing
{
    use FindsSameHeatServices;

    public function __construct(
        private readonly ResolveSettings $settings,
        private readonly NextNumber $nextNumber,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(Animal $sow, array $data, ?User $actor = null): Litter
    {
        return DB::transaction(function () use ($sow, $data, $actor) {
            $sow = Animal::lockForUpdate()->with('category')->findOrFail($sow->id);

            if ($replay = $this->replay($sow, $data['idempotency_key'] ?? null)) {
                return $replay;
            }

            $this->validate($sow, $data);
            $service = $this->resolveService($sow, $data);

            $farrowing = Farrowing::create([
                'sow_id' => $sow->id,
                'breeding_service_id' => $service?->id,
                'farrowed_on' => $data['farrowed_on'],
                'total_born' => $data['total_born'],
                'born_alive' => $data['born_alive'],
                'stillborn' => $data['stillborn'] ?? 0,
                'mummified' => $data['mummified'] ?? 0,
                'total_birth_weight_kg' => $data['total_birth_weight_kg'] ?? null,
                'assisted' => $data['assisted'] ?? false,
                'notes' => $data['notes'] ?? null,
                'user_id' => ($actor ?? Auth::user())?->getKey(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $service && $this->sameHeatServices($service)->each(
                fn (BreedingService $s) => $s->forceFill(['outcome' => ServiceOutcome::Farrowed, 'outcome_on' => $data['farrowed_on']])->save(),
            );

            $litter = Litter::create([
                'litter_number' => $this->litterNumber($sow, $data['farrowed_on']),
                'farrowing_id' => $farrowing->id,
                'sow_id' => $sow->id,
                'sire_id' => $service?->boar_id,
                'breeding_service_id' => $service?->id,
                'born_on' => $data['farrowed_on'],
                'expected_weaning_on' => $data['farrowed_on']->copy()->addDays((int) $this->settings->get('breeding.weaning_age_days')),
                'status' => LitterStatus::Suckling,
            ]);

            $this->promoteGilt($sow);
            FarrowingRecorded::dispatch($farrowing);

            return $litter;
        });
    }

    private function replay(Animal $sow, ?string $key): ?Litter
    {
        $existing = $key ? Farrowing::firstWhere('idempotency_key', $key) : null;

        if ($existing && $existing->sow_id !== $sow->id) {
            throw new DomainException('This idempotency key was already used for a different sow.', 'idempotency_conflict');
        }

        return $existing?->litter;
    }

    /** @param array<string, mixed> $data */
    private function validate(Animal $sow, array $data): void
    {
        $on = $data['farrowed_on'];
        $alive = $data['born_alive'];
        $dead = ($data['stillborn'] ?? 0) + ($data['mummified'] ?? 0);

        if (! $sow->isBreedingFemale() || ! $sow->isActive()) {
            throw new DomainException('Only an active sow or gilt can farrow.', 'not_breeding_female');
        }

        if ($on->isFuture() || ($sow->birth_date && $on->lt($sow->birth_date))) {
            throw new DomainException('The farrowing date must be between the sow\'s birth and today.', 'farrowing_date');
        }

        if ($data['total_born'] < 1 || $alive + $dead !== $data['total_born']) {
            throw new DomainException('Born alive + stillborn + mummified must equal the total born (at least 1).', 'litter_counts');
        }

        if (isset($data['total_birth_weight_kg']) && bccomp((string) $data['total_birth_weight_kg'], '0', 2) <= 0) {
            throw new DomainException('The litter birth weight must be positive.', 'birth_weight');
        }

        if (Litter::where('sow_id', $sow->id)->where('status', LitterStatus::Suckling->value)->exists()) {
            throw new DomainException("{$sow->animal_number} still has an unweaned litter.", 'sow_lactating');
        }

        if (Farrowing::where('sow_id', $sow->id)->whereDate('farrowed_on', $on)->exists()) {
            throw new DomainException("A farrowing is already recorded for {$sow->animal_number} on that date.", 'farrowing_duplicate');
        }
    }

    /**
     * The service that produced this litter: the one given, else the sow's open service
     * (confirmed pregnant first) expected to farrow closest to the actual date.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveService(Animal $sow, array $data): ?BreedingService
    {
        $on = $data['farrowed_on'];

        if (! empty($data['breeding_service_id'])) {
            $service = BreedingService::where('sow_id', $sow->id)->find($data['breeding_service_id'])
                ?? throw new DomainException('That service does not belong to this sow.', 'service_mismatch');

            if (! $service->outcome->isOpen() || $on->lte($service->serviced_on)) {
                throw new DomainException('That service cannot have produced this litter.', 'service_mismatch');
            }

            return $service;
        }

        return BreedingService::where('sow_id', $sow->id)
            ->whereIn('outcome', [ServiceOutcome::Pending->value, ServiceOutcome::Pregnant->value])
            ->where('serviced_on', '<', $on)
            ->get()
            ->sortBy(fn (BreedingService $s) => [$s->outcome === ServiceOutcome::Pregnant ? 0 : 1, abs($s->expected_farrowing_on->diffInDays($on))])
            ->first();
    }

    private function litterNumber(Animal $sow, CarbonInterface $farrowedOn): string
    {
        $prefix = strtoupper((string) $this->settings->get('animals.number_prefix'));
        $tag = str_replace('-', '', (string) preg_replace('/^[^-]+-/', '', $sow->animal_number));

        return sprintf('%s-%d-%s-L%02d', $prefix, $farrowedOn->year, $tag, ($this->nextNumber)("litter:{$sow->id}"));
    }

    private function promoteGilt(Animal $sow): void
    {
        if ($sow->category?->code !== 'gilt') {
            return;
        }

        $sowCategory = LookupValue::where('category', LookupCategory::AnimalCategory->value)->where('code', 'sow')->value('id');
        $sowCategory && $sow->update(['category_id' => $sowCategory]);
    }
}
