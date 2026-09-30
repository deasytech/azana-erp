<?php

namespace App\Filament\Resources\BiosecurityVisits\Pages;

use App\Domain\Biosecurity\Actions\RecordVisitorArrival;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\BiosecurityVisits\BiosecurityVisitResource;
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
            // Approval is the signing-in user's own authority, never someone else picked from a list.
            return app(RecordVisitorArrival::class)([
                ...$data,
                'arrived_at' => Carbon::parse($data['arrived_at']),
                'health_declaration' => (bool) ($data['health_declaration'] ?? false),
            ], auth()->user());
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
