<?php

namespace App\Filament\Resources\BiosecurityChecks\Pages;

use App\Domain\Biosecurity\Actions\RecordBiosecurityCheck;
use App\Domain\System\Exceptions\DomainException;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\BiosecurityChecks\BiosecurityCheckResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBiosecurityCheck extends CreateRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = BiosecurityCheckResource::class;

    protected static ?string $title = 'Record inspection';

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordBiosecurityCheck::class)(
                Carbon::parse($data['checked_on']),
                collect($data['results'] ?? [])->map(fn ($r) => ['item_id' => (int) $r['item_id'], 'passed' => (bool) $r['passed'], 'notes' => $r['notes'] ?? null])->all(),
                $data['production_unit_id'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            $this->failWith($e);
        }
    }
}
