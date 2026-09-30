<?php

namespace App\Filament\Resources\WithdrawalPeriods\Pages;

use App\Filament\Resources\WithdrawalPeriods\WithdrawalPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawalPeriods extends ListRecords
{
    protected static string $resource = WithdrawalPeriodResource::class;
}
