<?php

namespace App\Filament\Resources\BiosecurityVisits\Pages;

use App\Domain\Biosecurity\Actions\RecordVisitorArrival;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\BiosecurityVisits\BiosecurityVisitResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBiosecurityVisit extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = BiosecurityVisitResource::class;

    protected static ?string $title = 'Sign in visitor';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $approver = filled($data['approved_by'] ?? null) ? User::find($data['approved_by']) : null;

            return app(RecordVisitorArrival::class)([
                ...$data,
                'arrived_at' => Carbon::parse($data['arrived_at']),
                'health_declaration' => (bool) ($data['health_declaration'] ?? false),
            ], $approver);
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
