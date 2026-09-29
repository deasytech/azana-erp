<?php

namespace App\Filament\Resources\Animals\Pages;

use App\Domain\Animal\Actions\SetAnimalParentage;
use App\Domain\Animal\Models\Animal;
use App\Filament\Concerns\HandlesDomainExceptions;
use App\Filament\Resources\Animals\AnimalResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditAnimal extends EditRecord
{
    use HandlesDomainExceptions;

    protected static string $resource = AnimalResource::class;

    private const DETAIL_FIELDS = [
        'category_id', 'breed_id', 'genetic_line_id', 'birth_date', 'birth_date_estimated',
        'source', 'acquired_on', 'source_name', 'source_reference', 'purchase_price_minor', 'notes',
    ];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $parentage = $this->record->parentage;

        return $data + [
            'sire_id' => $parentage?->sire_id, 'dam_id' => $parentage?->dam_id,
            'sire_note' => $parentage?->sire_note, 'dam_note' => $parentage?->dam_note,
        ];
    }

    /** Position, status, weights and identifiers change only through their own actions. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Animal $record */
        return $this->attempt(function () use ($record, $data) {
            DB::transaction(function () use ($record, $data) {
                $record->update(Arr::only($data, self::DETAIL_FIELDS));
                app(SetAnimalParentage::class)($record, $data['sire_id'] ?? null, $data['dam_id'] ?? null, $data['sire_note'] ?? null, $data['dam_note'] ?? null);
            });

            return $record;
        });
    }
}
