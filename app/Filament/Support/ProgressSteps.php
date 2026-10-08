<?php

namespace App\Filament\Support;

/** Builds the step list the `x-erp.steps` stepper draws: a record's journey, from what has already happened. Display only. */
class ProgressSteps
{
    /**
     * @param  list<string>  $labels  every step in order
     * @param  int  $done  how many steps from the start are complete
     * @param  array<int, string>  $notes  small text under a step, by index
     * @return list<array{label: string, state: string, note: ?string}>
     */
    public static function make(array $labels, int $done, array $notes = []): array
    {
        return array_map(fn (string $label, int $i): array => [
            'label' => $label,
            'state' => $i < $done ? 'done' : ($i === $done ? 'current' : 'todo'),
            'note' => $notes[$i] ?? null,
        ], $labels, array_keys($labels));
    }

    /**
     * A journey that ended before its last step (rejected, cancelled): the completed steps, then one stopped step saying why.
     *
     * @param  list<string>  $completed
     * @return list<array{label: string, state: string, note: ?string}>
     */
    public static function stopped(array $completed, string $reason): array
    {
        return [
            ...array_map(fn (string $label): array => ['label' => $label, 'state' => 'done', 'note' => null], $completed),
            ['label' => $reason, 'state' => 'stopped', 'note' => null],
        ];
    }
}
