<?php

namespace App\Domain\Mobile\Services;

use App\Domain\Animal\Actions\LookupAnimal;
use App\Domain\Animal\Actions\RecordAnimalMovement;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Models\Animal;
use App\Domain\Breeding\Actions\RecordFarrowing;
use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Health\Actions\RecordTreatment;
use App\Domain\Health\Actions\RecordVaccination;
use App\Domain\Health\Models\Medicine;
use App\Domain\Inventory\Actions\RecordCountLine;
use App\Domain\Inventory\Actions\StartStockCount;
use App\Domain\Inventory\Actions\SubmitStockCount;
use App\Domain\Litter\Actions\RegisterLitterPiglets;
use App\Domain\Litter\Actions\WeanLitter;
use App\Domain\Litter\Models\Litter;
use App\Domain\Mobile\Exceptions\StaleData;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Actions\AdvanceTask;
use App\Domain\Tasks\Models\Task;
use App\Enums\ServiceMethod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The twelve things a worker does in the field, each by calling the same domain action the web screens call: this class only translates
 * what a device sends (scan codes, ids, numbers as text) into that action's arguments, and says which permission and which fields it needs.
 * Nothing here decides a business rule. The client's id is passed on as the action's idempotency key where the action has one.
 */
class QuickEntry
{
    /** The ids a handler cannot honestly look up from codes are plain numbers the device took from the reference data. */
    private const ID = ['integer', 'min:1'];

    public function __construct(
        private readonly LookupAnimal $lookup, private readonly RecordWeight $weight, private readonly RecordBatchWeighIn $weighIn, private readonly RecordFeedConsumption $feed,
        private readonly RecordTreatment $treatment, private readonly RecordVaccination $vaccination, private readonly RecordMortality $mortality,
        private readonly RecordBatchMortality $batchMortality, private readonly RecordAnimalMovement $movement, private readonly RecordService $service,
        private readonly RecordFarrowing $farrowing, private readonly WeanLitter $wean, private readonly RegisterLitterPiglets $piglets,
        private readonly StartStockCount $startCount, private readonly RecordCountLine $countLine, private readonly SubmitStockCount $submitCount, private readonly AdvanceTask $tasks,
    ) {}

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

    /**
     * Runs the quick action. Returns the record it made, as [type, id].
     *
     * @param  array<string, mixed>  $payload  already validated against rules()
     * @return array{0: string, 1: int}
     */
    public function run(string $type, array $payload, User $user, CarbonInterface $at, string $key): array
    {
        $record = DB::transaction(fn () => $this->{$this->definitions()[$type]['method']}($payload, $user, $at, $key));

        return [str(class_basename($record))->snake()->toString(), (int) $record->getKey()];
    }

    /** @return array<string, array{permission: string|callable, rules: callable, method: string}> */
    private function definitions(): array
    {
        $on = fn (array $p, string $animal, string $batch) => isset($p['batch']) ? $batch : $animal;   // a record about one pig, or about a whole batch

        return [
            'add_birth' => ['permission' => 'animals.create', 'method' => 'addBirth', 'rules' => fn () => [
                'litter' => 'required|string|max:40', 'pen_id' => ['nullable', ...self::ID], 'piglets' => 'required|array|min:1|max:30',
                'piglets.*.sex' => ['required', Rule::in(['male', 'female'])], 'piglets.*.birth_weight_kg' => 'nullable|numeric|gt:0',
            ]],
            'record_weight' => ['permission' => fn ($p) => $on($p, 'animals.create', 'production.create'), 'method' => 'recordWeight', 'rules' => fn ($p) => [
                'animal' => 'required_without:batch|string|max:120', 'batch' => 'required_without:animal|string|max:40|prohibits:animal',
                'weight_kg' => 'required_with:animal|numeric|gt:0', 'average_weight_kg' => 'required_with:batch|numeric|gt:0', 'sample_size' => ['required_with:batch', ...self::ID],
                'method' => 'nullable|string|max:20', 'notes' => 'nullable|string|max:500',
            ]],
            'record_feed' => ['permission' => 'production.create', 'method' => 'recordFeed', 'rules' => fn () => [
                'animal' => 'required_without:batch|string|max:120', 'batch' => 'required_without:animal|string|max:40|prohibits:animal',
                'feed_type_id' => ['required', ...self::ID], 'quantity_kg' => 'required|numeric|gt:0', 'inventory_location_id' => ['nullable', ...self::ID],
            ]],
            'record_treatment' => ['permission' => 'health.create', 'method' => 'recordTreatment', 'rules' => fn () => [
                'animal' => 'required|string|max:120', 'medicine_id' => ['required', ...self::ID], 'batch_id' => ['nullable', ...self::ID],
                'dose' => 'nullable|numeric|gt:0', 'dose_unit' => 'nullable|string|max:20', 'route' => 'nullable|string|max:40', 'notes' => 'nullable|string|max:500',
            ]],
            'record_vaccination' => ['permission' => 'health.create', 'method' => 'recordVaccination', 'rules' => fn () => [
                'animal' => 'required|string|max:120', 'schedule_id' => ['nullable', ...self::ID], 'medicine_id' => ['nullable', ...self::ID], 'batch_id' => ['nullable', ...self::ID],
                'dose' => 'nullable|numeric|gt:0', 'notes' => 'nullable|string|max:500',
            ]],
            'record_mortality' => ['permission' => fn ($p) => $on($p, 'health.create', 'production.create'), 'method' => 'recordMortality', 'rules' => fn () => [
                'animal' => 'required_without:batch|string|max:120', 'batch' => 'required_without:animal|string|max:40|prohibits:animal', 'count' => ['required_with:batch', ...self::ID],
                'cause_id' => ['required', ...self::ID], 'disease_id' => ['nullable', ...self::ID], 'notes' => 'nullable|string|max:500',
            ]],
            'move_pigs' => ['permission' => 'animals.edit', 'method' => 'movePigs', 'rules' => fn () => [
                'animal' => 'required|string|max:120', 'pen_id' => ['required_without:location_id', 'nullable', ...self::ID], 'location_id' => ['required_without:pen_id', 'nullable', ...self::ID],
                'reason_id' => ['nullable', ...self::ID], 'notes' => 'nullable|string|max:500',
            ]],
            'record_service' => ['permission' => 'breeding.create', 'method' => 'recordService', 'rules' => fn () => [
                'sow' => 'required|string|max:120', 'method' => ['required', Rule::enum(ServiceMethod::class)], 'boar' => 'nullable|string|max:120', 'semen_source' => 'nullable|string|max:120',
                'semen_batch_id' => ['nullable', ...self::ID], 'semen_location_id' => ['nullable', ...self::ID], 'doses' => 'nullable|integer|min:1|max:20', 'technician_name' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:500',
            ]],
            'record_farrowing' => ['permission' => 'breeding.create', 'method' => 'recordFarrowing', 'rules' => fn () => [
                'sow' => 'required|string|max:120', 'total_born' => 'required|integer|min:0|max:40', 'born_alive' => 'required|integer|min:0|max:40', 'stillborn' => 'nullable|integer|min:0|max:40',
                'mummified' => 'nullable|integer|min:0|max:40', 'total_birth_weight_kg' => 'nullable|numeric|gt:0', 'assisted' => 'nullable|boolean', 'breeding_service_id' => ['nullable', ...self::ID], 'notes' => 'nullable|string|max:500',
            ]],
            'record_weaning' => ['permission' => 'breeding.edit', 'method' => 'recordWeaning', 'rules' => fn () => [
                'litter' => 'required|string|max:40', 'weaned_count' => 'required|integer|min:0|max:40', 'total_weight_kg' => 'nullable|numeric|gt:0', 'destination_pen_id' => ['nullable', ...self::ID], 'notes' => 'nullable|string|max:500',
            ]],
            'stock_count' => ['permission' => 'inventory.create', 'method' => 'stockCount', 'rules' => fn () => [
                'location_id' => ['required', ...self::ID], 'notes' => 'nullable|string|max:500', 'lines' => 'required|array|min:1|max:200',
                'lines.*.item_id' => ['required', ...self::ID], 'lines.*.batch_id' => ['nullable', ...self::ID], 'lines.*.counted_quantity' => 'required|numeric|min:0',
                'lines.*.seen_quantity' => 'nullable|numeric|min:0', 'lines.*.reason' => 'nullable|string|max:255',
            ]],
            'complete_task' => ['permission' => 'tasks.edit', 'method' => 'completeTask', 'rules' => fn () => ['task' => 'required|string|max:20', 'notes' => 'nullable|string|max:1000']],
        ];
    }

    private function animal(string $code): Animal
    {
        return ($this->lookup)($code) ?? throw new DomainException("No animal matches {$code}.", 'not_found');
    }

    private function batch(string $code): ProductionBatch
    {
        return ProductionBatch::firstWhere('code', $code) ?? throw new DomainException("No batch matches {$code}.", 'not_found');
    }

    private function litter(string $number): Litter
    {
        return Litter::firstWhere('litter_number', $number) ?? throw new DomainException("No litter matches {$number}.", 'not_found');
    }

    /** @param array<string, mixed> $p */
    private function addBirth(array $p, User $user): Model
    {
        $litter = $this->litter($p['litter']);
        ($this->piglets)($litter, array_map(fn ($x) => ['sex' => $x['sex'], 'birth_weight_kg' => isset($x['birth_weight_kg']) ? (string) $x['birth_weight_kg'] : null], $p['piglets']), $p['pen_id'] ?? null, $user);

        return $litter;
    }

    /** @param array<string, mixed> $p */
    private function recordWeight(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        if (isset($p['batch'])) {
            return ($this->weighIn)($this->batch($p['batch']), $at, (int) $p['sample_size'], (string) $p['average_weight_kg'], $p['notes'] ?? null, $user, $key);
        }

        return ($this->weight)($this->animal($p['animal']), (string) $p['weight_kg'], $at, $p['method'] ?? 'scale', $p['notes'] ?? null, $user, $key);
    }

    /** @param array<string, mixed> $p */
    private function recordFeed(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        $target = isset($p['batch']) ? $this->batch($p['batch']) : $this->animal($p['animal']);

        return ($this->feed)($target, (int) $p['feed_type_id'], $at, (string) $p['quantity_kg'], ['inventory_location_id' => $p['inventory_location_id'] ?? null, 'idempotency_key' => $key], $user);
    }

    /** @param array<string, mixed> $p */
    private function recordTreatment(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        $medicine = Medicine::find($p['medicine_id']) ?? throw new DomainException('That medicine does not exist.', 'not_found');

        return ($this->treatment)($this->animal($p['animal']), $medicine, $at, [
            'batch_id' => $p['batch_id'] ?? null, 'dose' => $p['dose'] ?? null, 'dose_unit' => $p['dose_unit'] ?? null, 'route' => $p['route'] ?? null, 'notes' => $p['notes'] ?? null, 'idempotency_key' => $key,
        ], $user);
    }

    /** @param array<string, mixed> $p */
    private function recordVaccination(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        return ($this->vaccination)($this->animal($p['animal']), $at, [
            'schedule_id' => $p['schedule_id'] ?? null, 'medicine_id' => $p['medicine_id'] ?? null, 'batch_id' => $p['batch_id'] ?? null, 'dose' => $p['dose'] ?? null, 'notes' => $p['notes'] ?? null, 'idempotency_key' => $key,
        ], $user);
    }

    /** @param array<string, mixed> $p */
    private function recordMortality(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        if (isset($p['batch'])) {
            return ($this->batchMortality)($this->batch($p['batch']), (int) $p['count'], $at, (int) $p['cause_id'], $p['notes'] ?? null, $user, $key);
        }

        return ($this->mortality)($this->animal($p['animal']), $at, (int) $p['cause_id'], $p['disease_id'] ?? null, $p['notes'] ?? null, $user);
    }

    /** @param array<string, mixed> $p */
    private function movePigs(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        return ($this->movement)($this->animal($p['animal']), $p['pen_id'] ?? null, $p['location_id'] ?? null, $at, $p['reason_id'] ?? null, $p['notes'] ?? null, $user, $key);
    }

    /** @param array<string, mixed> $p */
    private function recordService(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        $boar = filled($p['boar'] ?? null) ? $this->animal($p['boar']) : null;

        return ($this->service)($this->animal($p['sow']), ServiceMethod::from($p['method']), $at, $boar?->id, $p['semen_source'] ?? null, null, $p['technician_name'] ?? null, $p['notes'] ?? null,
            $user, $key, $p['semen_batch_id'] ?? null, $p['semen_location_id'] ?? null, (int) ($p['doses'] ?? 1));
    }

    /** @param array<string, mixed> $p */
    private function recordFarrowing(array $p, User $user, CarbonInterface $at, string $key): Model
    {
        return ($this->farrowing)($this->animal($p['sow']), [
            'farrowed_on' => $at, 'total_born' => (int) $p['total_born'], 'born_alive' => (int) $p['born_alive'], 'stillborn' => (int) ($p['stillborn'] ?? 0), 'mummified' => (int) ($p['mummified'] ?? 0),
            'total_birth_weight_kg' => isset($p['total_birth_weight_kg']) ? (string) $p['total_birth_weight_kg'] : null, 'assisted' => (bool) ($p['assisted'] ?? false),
            'breeding_service_id' => $p['breeding_service_id'] ?? null, 'notes' => $p['notes'] ?? null, 'idempotency_key' => $key,
        ], $user);
    }

    /** @param array<string, mixed> $p */
    private function recordWeaning(array $p, User $user, CarbonInterface $at): Model
    {
        return ($this->wean)($this->litter($p['litter']), $at, (int) $p['weaned_count'], isset($p['total_weight_kg']) ? (string) $p['total_weight_kg'] : null, $p['destination_pen_id'] ?? null, $p['notes'] ?? null, $user);
    }

    /**
     * Opens a count, enters every line and submits it. Each line may carry the quantity the device was shown (seen_quantity); if the
     * system quantity is not that any more, the count is abandoned as stale rather than entered against stock that has moved.
     *
     * @param  array<string, mixed>  $p
     */
    private function stockCount(array $p, User $user, CarbonInterface $at): Model
    {
        $count = ($this->startCount)((int) $p['location_id'], $at, $p['notes'] ?? null, $user);
        $system = $count->lines()->get()->mapWithKeys(fn ($l) => [$l->inventory_item_id.'|'.($l->inventory_batch_id ?? '') => (string) $l->system_quantity]);

        foreach ($p['lines'] as $line) {
            $seen = $line['seen_quantity'] ?? null;
            $now = $system[$line['item_id'].'|'.($line['batch_id'] ?? '')] ?? '0.000';

            if ($seen !== null && bccomp((string) $seen, $now, 3) !== 0) {
                throw new StaleData("The system now shows {$now} for item {$line['item_id']}, not the {$seen} the device was shown. Count again.", 'stale_count');
            }

            ($this->countLine)($count, (int) $line['item_id'], $line['batch_id'] ?? null, (string) $line['counted_quantity'], $line['reason'] ?? null);
        }

        return ($this->submitCount)($count, $user);
    }

    /** @param array<string, mixed> $p */
    private function completeTask(array $p, User $user): Model
    {
        $task = Task::firstWhere('number', $p['task']) ?? throw new DomainException("No task matches {$p['task']}.", 'not_found');

        return $this->tasks->complete($task, $user, $p['notes'] ?? null);
    }
}
