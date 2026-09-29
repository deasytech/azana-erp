<?php

namespace App\Filament\Resources\HeatEvents\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordHeat;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\HeatEvents\HeatEventResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHeatEvent extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = HeatEventResource::class;

    protected static ?string $title = 'Record heat';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordHeat::class)(Animal::findOrFail($data['sow_id']), Carbon::parse($data['detected_on']), $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
