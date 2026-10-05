<?php

namespace App\Filament\Resources\FeedFormulas\Pages;

use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFeedFormula extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = FeedFormulaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->map(fn ($i) => [
            'inventory_item_id' => $i->inventory_item_id, 'inclusion_percent' => (string) $i->inclusion_percent, 'notes' => $i->notes,
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveFeedFormula::class)($data, $record);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return FeedFormulaResource::getUrl('view', ['record' => $this->record]);
    }
}
