<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBudget extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = BudgetResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines->map(fn ($l) => ['account_id' => $l->account_id, 'cost_centre_id' => $l->cost_centre_id, 'month' => $l->month, 'amount_minor' => $l->amount_minor])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveBudget::class)($data['name'], (int) $data['fiscal_year'], CreateBudget::lines($data), $data['notes'] ?? null, $record);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
