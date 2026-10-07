<?php

namespace App\Filament\Resources\CashTransactions\Pages;

use App\Domain\Finance\Actions\RecordCashTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CashDirection;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\CashTransactions\CashTransactionResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCashTransaction extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = CashTransactionResource::class;

    protected static ?string $title = 'Record cash transaction';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordCashTransaction::class)(Carbon::parse($data['transaction_date']), CashDirection::from($data['direction']), (int) $data['cash_account_id'], (int) $data['counter_account_id'],
                (int) $data['amount_minor'], filled($data['cost_centre_id'] ?? null) ? (int) $data['cost_centre_id'] : null, $data['reference'] ?? null, $data['description'] ?? null);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
