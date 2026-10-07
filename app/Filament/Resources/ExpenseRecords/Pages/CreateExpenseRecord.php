<?php

namespace App\Filament\Resources\ExpenseRecords\Pages;

use App\Domain\Finance\Actions\RecordExpense;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\ExpenseRecords\ExpenseRecordResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpenseRecord extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = ExpenseRecordResource::class;

    protected static ?string $title = 'Record expense';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordExpense::class)(Carbon::parse($data['expense_date']), (int) $data['account_id'], (int) $data['cost_centre_id'], (int) $data['amount_minor'],
                filled($data['paid_from_account_id'] ?? null) ? (int) $data['paid_from_account_id'] : null, $data['payee'] ?? null, $data['reference'] ?? null, $data['description'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
