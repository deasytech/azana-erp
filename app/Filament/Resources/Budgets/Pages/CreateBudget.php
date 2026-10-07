<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Domain\Finance\Actions\SaveBudget;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBudget extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = BudgetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveBudget::class)($data['name'], (int) $data['fiscal_year'], static::lines($data), $data['notes'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }

    /** @return list<array<string, int|null>> */
    public static function lines(array $data): array
    {
        return array_map(fn (array $l) => ['account_id' => (int) $l['account_id'], 'cost_centre_id' => filled($l['cost_centre_id'] ?? null) ? (int) $l['cost_centre_id'] : null, 'month' => (int) $l['month'], 'amount_minor' => (int) $l['amount_minor']], $data['lines']);
    }
}
