<?php

namespace App\Domain\Health\Actions;

use App\Domain\Animal\Models\Animal;
use App\Domain\Health\Models\QuarantineRecord;
use App\Domain\Health\Models\WithdrawalPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** What currently stops an animal from being sold or slaughtered. */
class GetAnimalRestrictions
{
    /** @return array{withdrawals: Collection<int, WithdrawalPeriod>, quarantine: ?QuarantineRecord} */
    public function __invoke(Animal $animal, ?CarbonInterface $on = null): array
    {
        $on ??= now();

        return [
            'withdrawals' => WithdrawalPeriod::with('medicine')
                ->where('animal_id', $animal->id)
                ->whereNull('cleared_at')
                ->whereDate('ends_on', '>', $on->toDateString())
                ->orderByDesc('ends_on')
                ->get(),
            'quarantine' => QuarantineRecord::where('animal_id', $animal->id)->whereNull('released_on')->first(),
        ];
    }
}
