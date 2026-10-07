<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\KpiRegistry;
use App\Domain\Reporting\Models\KpiTarget;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Sets the target of an indicator for a year, or for one month of it (a month's own target wins over the year's). One target per
 * indicator and period: setting it again changes the value (a unique index on the indicator, year and period backs the check, so a simultaneous save cannot slip a second row in).
 */
class SetKpiTarget
{
    private const DUPLICATE = 'There is already a target for that indicator and period; edit it instead.';

    public function __invoke(string $kpiKey, int $year, ?int $month, string $value, ?string $notes = null, ?KpiTarget $existing = null, ?User $actor = null): KpiTarget
    {
        isset(KpiRegistry::all()[$kpiKey]) || throw new DomainException('Choose one of the farm\'s indicators.', 'kpi_unknown');

        ($year < 2000 || $year > 2100 || ($month !== null && ($month < 1 || $month > 12))) && throw new DomainException('Give a year, and a month from 1 to 12 or none for the whole year.', 'kpi_period');

        if (! preg_match('/^\d{1,14}(\.\d{1,4})?$/', $value)) {
            throw new DomainException('A target is a number of zero or more (at most 4 decimals). Money is in minor units.', 'kpi_value');
        }

        try {
            return $this->save($kpiKey, $year, $month, $value, $notes, $existing, $actor);
        } catch (UniqueConstraintViolationException) {
            // The database's own unique index caught what the check below could not: two people saving the same period at once.
            throw new DomainException(self::DUPLICATE, 'kpi_duplicate');
        }
    }

    private function save(string $kpiKey, int $year, ?int $month, string $value, ?string $notes, ?KpiTarget $existing, ?User $actor): KpiTarget
    {
        return DB::transaction(function () use ($kpiKey, $year, $month, $value, $notes, $existing, $actor) {
            $clash = KpiTarget::where(['kpi_key' => $kpiKey, 'year' => $year, 'month' => $month])->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->lockForUpdate()->first();

            if ($clash) {
                throw new DomainException(self::DUPLICATE, 'kpi_duplicate');
            }

            $target = $existing ? KpiTarget::lockForUpdate()->findOrFail($existing->id) : new KpiTarget(['created_by' => ($actor ?? Auth::user())?->getKey()]);
            $target->fill(['kpi_key' => $kpiKey, 'year' => $year, 'month' => $month, 'target_value' => $value, 'notes' => $notes])->save();

            return $target;
        });
    }
}
