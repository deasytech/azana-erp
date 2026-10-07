<?php

namespace App\Domain\Mobile\Services;

use App\Enums\ServiceMethod;
use Illuminate\Validation\Rule;

/**
 * What each of the twelve quick actions is: the permission it needs, the fields its payload takes (as validation rules), and which
 * QuickEntry method does it. Nothing here runs anything; the sync layer, the reference lists and the API reference page all read it.
 */
class QuickCatalogue
{
    /** The ids a handler cannot honestly look up from codes are plain numbers the device took from the reference data. */
    private const ID = ['integer', 'min:1'];

    private const POSITIVE = 'nullable|numeric|gt:0';

    private const NOTES = 'nullable|string|max:500';

    private const CODE = 'required|string|max:120';

    private const OPTIONAL_CODE = 'nullable|string|max:120';

    private const COUNT = 'required|integer|min:0|max:40';

    private const ANIMAL_OR_BATCH = 'required_without:batch|string|max:120';

    private const BATCH_OR_ANIMAL = 'required_without:animal|string|max:40|prohibits:animal';

    private const PRODUCTION = 'production.create';

    private const HEALTH = 'health.create';

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->definitions());
    }

    public function knows(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    /** @param array<string, mixed> $payload */
    public function permission(string $type, array $payload): string
    {
        $permission = $this->definitions()[$type]['permission'];

        return is_callable($permission) ? $permission($payload) : $permission;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function rules(string $type, array $payload): array
    {
        return $this->definitions()[$type]['rules']($payload);
    }

    public function handler(string $type): string
    {
        return $this->definitions()[$type]['method'];
    }

    /** @return array<string, array{permission: string|callable, rules: callable, method: string}> */
    private function definitions(): array
    {
        $on = fn (array $p, string $animal, string $batch) => isset($p['batch']) ? $batch : $animal;   // a record about one pig, or about a whole batch

        return [
            'add_birth' => ['permission' => 'animals.create', 'method' => 'addBirth', 'rules' => fn () => [
                'litter' => 'required|string|max:40', 'pen_id' => ['nullable', ...self::ID], 'piglets' => 'required|array|min:1|max:30',
                'piglets.*.sex' => ['required', Rule::in(['male', 'female'])], 'piglets.*.birth_weight_kg' => self::POSITIVE,
            ]],
            'record_weight' => ['permission' => fn ($p) => $on($p, 'animals.create', self::PRODUCTION), 'method' => 'recordWeight', 'rules' => fn ($p) => [
                'animal' => self::ANIMAL_OR_BATCH, 'batch' => self::BATCH_OR_ANIMAL,
                'weight_kg' => 'required_with:animal|numeric|gt:0', 'average_weight_kg' => 'required_with:batch|numeric|gt:0', 'sample_size' => ['required_with:batch', ...self::ID],
                'method' => 'nullable|string|max:20', 'notes' => self::NOTES,
            ]],
            'record_feed' => ['permission' => self::PRODUCTION, 'method' => 'recordFeed', 'rules' => fn () => [
                'animal' => self::ANIMAL_OR_BATCH, 'batch' => self::BATCH_OR_ANIMAL,
                'feed_type_id' => ['required', ...self::ID], 'quantity_kg' => 'required|numeric|gt:0', 'inventory_location_id' => ['nullable', ...self::ID],
            ]],
            'record_treatment' => ['permission' => self::HEALTH, 'method' => 'recordTreatment', 'rules' => fn () => [
                'animal' => self::CODE, 'medicine_id' => ['required', ...self::ID], 'batch_id' => ['nullable', ...self::ID],
                'dose' => self::POSITIVE, 'dose_unit' => 'nullable|string|max:20', 'route' => 'nullable|string|max:40', 'notes' => self::NOTES,
            ]],
            'record_vaccination' => ['permission' => self::HEALTH, 'method' => 'recordVaccination', 'rules' => fn () => [
                'animal' => self::CODE, 'schedule_id' => ['nullable', ...self::ID], 'medicine_id' => ['nullable', ...self::ID], 'batch_id' => ['nullable', ...self::ID],
                'dose' => self::POSITIVE, 'notes' => self::NOTES,
            ]],
            'record_mortality' => ['permission' => fn ($p) => $on($p, self::HEALTH, self::PRODUCTION), 'method' => 'recordMortality', 'rules' => fn () => [
                'animal' => self::ANIMAL_OR_BATCH, 'batch' => self::BATCH_OR_ANIMAL, 'count' => ['required_with:batch', ...self::ID],
                'cause_id' => ['required', ...self::ID], 'disease_id' => ['nullable', ...self::ID], 'notes' => self::NOTES,
            ]],
            'move_pigs' => ['permission' => 'animals.edit', 'method' => 'movePigs', 'rules' => fn () => [
                'animal' => self::CODE, 'pen_id' => ['required_without:location_id', 'nullable', ...self::ID], 'location_id' => ['required_without:pen_id', 'nullable', ...self::ID],
                'reason_id' => ['nullable', ...self::ID], 'notes' => self::NOTES,
            ]],
            'record_service' => ['permission' => 'breeding.create', 'method' => 'recordService', 'rules' => fn () => [
                'sow' => self::CODE, 'method' => ['required', Rule::enum(ServiceMethod::class)], 'boar' => self::OPTIONAL_CODE, 'semen_source' => self::OPTIONAL_CODE,
                'semen_batch_id' => ['nullable', ...self::ID], 'semen_location_id' => ['nullable', ...self::ID], 'doses' => 'nullable|integer|min:1|max:20', 'technician_name' => self::OPTIONAL_CODE, 'notes' => self::NOTES,
            ]],
            'record_farrowing' => ['permission' => 'breeding.create', 'method' => 'recordFarrowing', 'rules' => fn () => [
                'sow' => self::CODE, 'total_born' => self::COUNT, 'born_alive' => self::COUNT, 'stillborn' => 'nullable|integer|min:0|max:40',
                'mummified' => 'nullable|integer|min:0|max:40', 'total_birth_weight_kg' => self::POSITIVE, 'assisted' => 'nullable|boolean', 'breeding_service_id' => ['nullable', ...self::ID], 'notes' => self::NOTES,
            ]],
            'record_weaning' => ['permission' => 'breeding.edit', 'method' => 'recordWeaning', 'rules' => fn () => [
                'litter' => 'required|string|max:40', 'weaned_count' => self::COUNT, 'total_weight_kg' => self::POSITIVE, 'destination_pen_id' => ['nullable', ...self::ID], 'notes' => self::NOTES,
            ]],
            'stock_count' => ['permission' => 'inventory.create', 'method' => 'stockCount', 'rules' => fn () => [
                'location_id' => ['required', ...self::ID], 'notes' => self::NOTES, 'lines' => 'required|array|min:1|max:200',
                'lines.*.item_id' => ['required', ...self::ID], 'lines.*.batch_id' => ['nullable', ...self::ID], 'lines.*.counted_quantity' => 'required|numeric|min:0',
                'lines.*.seen_quantity' => 'nullable|numeric|min:0', 'lines.*.reason' => 'nullable|string|max:255',
            ]],
            'complete_task' => ['permission' => 'tasks.edit', 'method' => 'completeTask', 'rules' => fn () => ['task' => 'required|string|max:20', 'notes' => 'nullable|string|max:1000']],
        ];
    }
}
