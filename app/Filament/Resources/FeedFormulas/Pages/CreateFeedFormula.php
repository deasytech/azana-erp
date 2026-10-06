<?php

namespace App\Filament\Resources\FeedFormulas\Pages;

use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\FeedFormulas\FeedFormulaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFeedFormula extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = FeedFormulaResource::class;

    protected static ?string $title = 'New feed formula';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveFeedFormula::class)($data);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return FeedFormulaResource::getUrl('view', ['record' => $this->record]);
    }
}
