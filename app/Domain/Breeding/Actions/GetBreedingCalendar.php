<?php

namespace App\Domain\Breeding\Actions;

use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Litter\Models\Litter;
use App\Domain\Litter\Models\WeaningRecord;
use App\Enums\LitterStatus;
use App\Enums\ServiceOutcome;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Expected breeding events between two dates, from the dates snapshotted at service and weaning. */
class GetBreedingCalendar
{
    /** @return Collection<int, array{date: CarbonInterface, type: string, sow: string, detail: string, sow_id: int}> */
    public function __invoke(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $entries = collect();
        $open = [ServiceOutcome::Pending->value, ServiceOutcome::Pregnant->value];

        $services = fn (string $column, array $outcomes) => BreedingService::with('sow')
            ->whereIn('outcome', $outcomes)->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString())->get();

        foreach ($services('expected_pregnancy_check_on', [ServiceOutcome::Pending->value]) as $s) {
            $entries->push($this->entry($s->expected_pregnancy_check_on, 'Pregnancy check due', $s->sow, "Served {$s->serviced_on->format('d M')}"));
        }

        foreach ($services('expected_next_heat_on', [ServiceOutcome::Pending->value]) as $s) {
            $entries->push($this->entry($s->expected_next_heat_on, 'Watch for return to heat', $s->sow, "Served {$s->serviced_on->format('d M')}"));
        }

        foreach ($services('expected_farrowing_on', $open) as $s) {
            $entries->push($this->entry($s->expected_farrowing_on, 'Farrowing expected', $s->sow, $s->outcome->label()));
        }

        foreach (Litter::with('sow')->where('status', LitterStatus::Suckling->value)->whereDate('expected_weaning_on', '>=', $from->toDateString())->whereDate('expected_weaning_on', '<=', $to->toDateString())->get() as $l) {
            $entries->push($this->entry($l->expected_weaning_on, 'Weaning due', $l->sow, $l->litter_number));
        }

        foreach ($this->sowsAwaitingService($from, $to) as $w) {
            $entries->push($this->entry($w->expected_next_service_on, 'Next service due', $w->litter->sow, "Weaned {$w->weaned_on->format('d M')}"));
        }

        return $entries->sortBy(fn ($e) => $e['date']->getTimestamp())->values();
    }

    /** Weaned sows not yet served since weaning. */
    private function sowsAwaitingService(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return WeaningRecord::with('litter.sow')
            ->whereDate('expected_next_service_on', '>=', $from->toDateString())->whereDate('expected_next_service_on', '<=', $to->toDateString())
            ->get()
            ->reject(fn (WeaningRecord $w) => BreedingService::where('sow_id', $w->litter->sow_id)->where('serviced_on', '>=', $w->weaned_on)->exists());
    }

    /** @return array{date: CarbonInterface, type: string, sow: string, detail: string, sow_id: int} */
    private function entry(CarbonInterface $date, string $type, $sow, string $detail): array
    {
        return ['date' => $date, 'type' => $type, 'sow' => $sow->animal_number, 'detail' => $detail, 'sow_id' => $sow->id];
    }
}
