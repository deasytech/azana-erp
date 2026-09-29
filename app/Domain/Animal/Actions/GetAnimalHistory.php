<?php

namespace App\Domain\Animal\Actions;

use App\Domain\Animal\Models\Animal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** The animal's lifecycle as one chronological list (newest first) for the profile page. */
class GetAnimalHistory
{
    /** @return Collection<int, array{at: CarbonInterface, type: string, title: string, detail: ?string, user: ?string}> */
    public function __invoke(Animal $animal): Collection
    {
        $entries = collect();

        foreach ($animal->statusHistory()->with('user')->get() as $s) {
            $entries->push($this->entry($s->changed_at, 'Status', $s->from_status ? "{$s->from_status->label()} → {$s->to_status->label()}" : "Registered as {$s->to_status->label()}", $s->reason, $s->user?->name));
        }

        foreach ($animal->movements()->with(['fromPen', 'fromLocation', 'toPen', 'toLocation', 'reason', 'user'])->get() as $m) {
            $entries->push($this->entry($m->moved_at, 'Movement', "{$m->fromLabel()} → {$m->toLabel()}", collect([$m->reason?->name, $m->notes])->filter()->implode(' - ') ?: null, $m->user?->name));
        }

        foreach ($animal->weights()->with('user')->get() as $w) {
            $entries->push($this->entry($w->weighed_at, 'Weight', "{$w->weight_kg} kg".($w->isVoided() ? ' (voided)' : ''), $w->isVoided() ? $w->void_reason : $w->notes, $w->user?->name));
        }

        foreach ($animal->identifiers as $i) {
            $entries->push($this->entry($i->created_at, 'Identifier', "{$i->type->label()} {$i->value} added", null, null));

            if ($i->retired_at) {
                $entries->push($this->entry($i->retired_at, 'Identifier', "{$i->type->label()} {$i->value} retired", $i->retired_reason, null));
            }
        }

        return $entries->sortByDesc(fn ($e) => $e['at']->getTimestamp())->values();
    }

    /** @return array{at: CarbonInterface, type: string, title: string, detail: ?string, user: ?string} */
    private function entry($at, string $type, string $title, ?string $detail, ?string $user): array
    {
        return compact('at', 'type', 'title', 'detail', 'user');
    }
}
