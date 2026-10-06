<?php

namespace App\Filament\Resources\MeatProductionBatches\Pages;

use App\Domain\Meat\Actions\ProduceMeat;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\MeatProductionBatches\MeatProductionBatchResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeatProductionBatch extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = MeatProductionBatchResource::class;

    protected static ?string $title = 'Make meat from carcasses';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProduceMeat::class)(
                array_map('intval', $data['carcass_ids']), (int) $data['inventory_location_id'], Carbon::parse($data['produced_on']),
                array_map(fn (array $line) => ['meat_product_id' => (int) $line['meat_product_id'], 'weight_kg' => (string) $line['weight_kg']], $data['lines']),
                (string) ($data['waste_kg'] ?? '0'), (int) ($data['other_cost_minor'] ?? 0), $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return MeatProductionBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
