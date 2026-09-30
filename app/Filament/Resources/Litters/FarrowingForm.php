<?php

namespace App\Filament\Resources\Litters;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordFarrowing;
use App\Domain\Litter\Models\Litter;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;

/** The farrowing entry form and its hand-off to the RecordFarrowing action (used from several screens). */
class FarrowingForm
{
    /** @return list<Component> */
    public static function fields(): array
    {
        return [
            DatePicker::make('farrowed_on')->required()->default(now())->maxDate(now()),
            TextInput::make('born_alive')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('stillborn')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('mummified')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('total_birth_weight_kg')->label('Litter birth weight (kg, optional)')->numeric()->minValue(0.01)->step(0.01),
            Toggle::make('assisted')->label('Assisted farrowing'),
            Textarea::make('notes'),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function submit(Animal $sow, array $data, ?int $serviceId = null): Litter
    {
        return app(RecordFarrowing::class)($sow, [
            'farrowed_on' => Carbon::parse($data['farrowed_on']),
            'total_born' => (int) $data['born_alive'] + (int) $data['stillborn'] + (int) $data['mummified'],
            'born_alive' => (int) $data['born_alive'],
            'stillborn' => (int) $data['stillborn'],
            'mummified' => (int) $data['mummified'],
            'total_birth_weight_kg' => filled($data['total_birth_weight_kg'] ?? null) ? (string) $data['total_birth_weight_kg'] : null,
            'assisted' => (bool) ($data['assisted'] ?? false),
            'notes' => $data['notes'] ?? null,
            'breeding_service_id' => $serviceId,
        ]);
    }
}
