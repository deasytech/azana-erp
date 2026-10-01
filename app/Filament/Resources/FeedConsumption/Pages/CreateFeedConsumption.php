<?php

namespace App\Filament\Resources\FeedConsumption\Pages;

use App\Domain\Animal\Models\Animal;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\FeedConsumption\FeedConsumptionResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFeedConsumption extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = FeedConsumptionResource::class;

    protected static ?string $title = 'Record feed';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $target = ($data['target'] ?? 'batch') === 'animal'
                ? Animal::findOrFail($data['animal_id'])
                : ProductionBatch::findOrFail($data['production_batch_id']);

            return app(RecordFeedConsumption::class)($target, (int) $data['feed_type_id'], Carbon::parse($data['consumed_on']), (string) $data['quantity_kg'], [
                'cost_per_kg_minor' => $data['cost_per_kg_minor'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
