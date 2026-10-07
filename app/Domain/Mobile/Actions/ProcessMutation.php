<?php

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\Exceptions\StaleData;
use App\Domain\Mobile\Models\SyncMutation;
use App\Domain\Mobile\Services\QuickEntry;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Takes one mutation from a device and either does it, or says why not - exactly once, however often the device sends it.
 *
 * The mutation's client_id is the idempotency key. The first time it arrives the quick action runs and the outcome is stored; every
 * later time the stored outcome is returned and nothing runs again. Outcomes:
 *  - accepted: done (server_type / server_id say what was made)
 *  - rejected: the request is wrong (unknown type, not allowed, invalid fields, or a business rule the data breaks); sending it again will not help
 *  - conflict: the server has moved on since the device saw it (animal already dead, litter already weaned, stock changed...); a person must look
 *  - failed:   an unexpected server error; nothing was recorded, and the device may send the same mutation again
 */
class ProcessMutation
{
    /** Rule codes that mean "the world has changed since the device looked", as opposed to "this request is wrong". */
    private const CONFLICT_CODES = [
        'animal_not_active', 'status_final', 'litter_weaned', 'task_state', 'sow_pregnant', 'sow_lactating', 'farrowing_duplicate', 'vaccination_duplicate', 'weigh_in_duplicate',
        'movement_same_place', 'movement_out_of_order', 'service_out_of_order', 'pen_full', 'idempotency_conflict', 'stale_count', 'count_not_draft', 'insufficient_stock', 'feed_not_stocked',
    ];

    public function __construct(private readonly QuickEntry $quickEntry) {}

    /**
     * @param  array<string, mixed>  $mutation  client_id, type, occurred_at, payload
     * @return array<string, mixed> the outcome, ready for the response
     */
    public function __invoke(User $user, string $deviceId, array $mutation): array
    {
        $envelope = Validator::make($mutation + ['payload' => null], [
            'client_id' => 'required|uuid', 'type' => 'required|string|max:40', 'occurred_at' => 'required|date', 'payload' => 'required|array',
        ]);

        if ($envelope->fails()) {
            return $this->unstored($mutation['client_id'] ?? null, 'invalid_envelope', 'Each mutation needs a client_id (uuid), a type, an occurred_at and a payload.', $envelope->errors()->toArray());
        }

        $occurredAt = Carbon::parse($mutation['occurred_at']);

        if ($occurredAt->gt(now()->addMinutes(5))) {
            return $this->unstored($mutation['client_id'], 'invalid_envelope', 'occurred_at is in the future: check the device clock.', ['occurred_at' => ['In the future.']]);
        }

        return DB::transaction(function () use ($user, $deviceId, $mutation, $occurredAt) {
            [$row, $seenBefore] = $this->claim($user, $deviceId, $mutation, $occurredAt);

            if ($row->user_id !== $user->id || $row->device_id !== $deviceId) {
                return $this->unstored($row->client_id, 'idempotency_conflict', 'That client_id was already used by another user or device.');
            }

            // A final answer is simply given again. Only a failed one (the server's fault) is run again.
            return $seenBefore && $row->isFinal() ? $this->present($row, true) : $this->present($this->attempt($row, $user, $seenBefore), false);
        });
    }

    /**
     * Stores the mutation if its client_id is new, and returns its row, locked.
     *
     * The insert ignores a client_id that already exists, in one atomic statement, so two requests with the same client_id (a double tap, a
     * retry on a bad connection) cannot fail on each other: whichever got there first stored it, and the other finds the row and waits for the
     * first to finish (the row lock) before it looks.
     *
     * @return array{0: SyncMutation, 1: bool} the row, and whether the device had sent it before
     */
    private function claim(User $user, string $deviceId, array $m, Carbon $at): array
    {
        $now = now();
        $stored = SyncMutation::query()->insertOrIgnore([
            'client_id' => $m['client_id'], 'device_id' => $deviceId, 'user_id' => $user->id, 'type' => $m['type'], 'occurred_at' => $at->toDateTimeString(),
            'payload' => json_encode($m['payload']), 'status' => SyncMutation::FAILED, 'attempted_at' => $now->toDateTimeString(),
            'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString(),
        ]);

        return [SyncMutation::where('client_id', $m['client_id'])->lockForUpdate()->firstOrFail(), $stored === 0];
    }

    private function attempt(SyncMutation $row, User $user, bool $retry): SyncMutation
    {
        $at = $row->occurred_at;
        $row->fill(['attempts' => $retry ? $row->attempts + 1 : 1, 'attempted_at' => now(), 'error_code' => null, 'error_message' => null, 'errors' => null]);
        $type = $row->type;
        $payload = $row->payload;

        if (! $this->quickEntry->knows($type)) {
            return $this->finish($row, SyncMutation::REJECTED, 'unknown_type', "There is no quick action called {$type}.");
        }

        if (! $user->is_active || ! $user->can($this->quickEntry->permission($type, $payload))) {
            return $this->finish($row, SyncMutation::REJECTED, 'forbidden', 'You are not allowed to do this.');
        }

        $fields = Validator::make($payload, $this->quickEntry->rules($type, $payload));

        if ($fields->fails()) {
            return $this->finish($row, SyncMutation::REJECTED, 'invalid_payload', 'Some fields are missing or wrong.', $fields->errors()->toArray());
        }

        try {
            [$serverType, $serverId] = $this->quickEntry->run($type, $fields->validated(), $user, $at, $row->client_id);
        } catch (StaleData $e) {
            return $this->finish($row, SyncMutation::CONFLICT, $e->errorCode(), $e->getMessage());
        } catch (DomainException $e) {
            return $this->finish($row, in_array($e->errorCode(), self::CONFLICT_CODES, true) ? SyncMutation::CONFLICT : SyncMutation::REJECTED, $e->errorCode(), $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->finish($row, SyncMutation::FAILED, 'server_error', 'The server could not record this. Nothing was saved; try again.');
        }

        $row->fill(['server_type' => $serverType, 'server_id' => $serverId, 'synced_at' => now()]);

        return $this->finish($row, SyncMutation::ACCEPTED);
    }

    /** @param array<string, list<string>>|null $errors */
    private function finish(SyncMutation $row, string $status, ?string $code = null, ?string $message = null, ?array $errors = null): SyncMutation
    {
        $row->fill(['status' => $status, 'error_code' => $code, 'error_message' => $message, 'errors' => $errors])->save();

        return $row;
    }

    /** @return array<string, mixed> */
    private function present(SyncMutation $row, bool $replayed): array
    {
        return [
            'client_id' => $row->client_id, 'status' => $row->status, 'replayed' => $replayed, 'server_type' => $row->server_type, 'server_id' => $row->server_id,
            'error' => $row->status === SyncMutation::ACCEPTED ? null : ['code' => $row->error_code, 'message' => $row->error_message, 'fields' => $row->errors],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array<string, mixed>
     */
    private function unstored(?string $clientId, string $code, string $message, ?array $fields = null): array
    {
        return ['client_id' => $clientId, 'status' => SyncMutation::REJECTED, 'replayed' => false, 'server_type' => null, 'server_id' => null, 'error' => ['code' => $code, 'message' => $message, 'fields' => $fields]];
    }
}
