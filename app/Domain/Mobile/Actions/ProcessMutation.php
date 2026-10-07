<?php

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\Exceptions\StaleData;
use App\Domain\Mobile\Models\SyncMutation;
use App\Domain\Mobile\Services\QuickCatalogue;
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

    public function __construct(private readonly QuickEntry $quickEntry, private readonly QuickCatalogue $catalogue) {}

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
        $row->fill(['attempts' => $retry ? $row->attempts + 1 : 1, 'attempted_at' => now(), 'error_code' => null, 'error_message' => null, 'errors' => null]);

        // [status, code, message, fields]: why it was refused, or null when it may be run.
        $outcome = $this->refusal($row, $user) ?? $this->execute($row, $user);

        return $this->finish($row, ...$outcome);
    }

    /**
     * Why this mutation cannot be run at all, if it cannot.
     *
     * @return ?array{0: string, 1: ?string, 2: ?string, 3: ?array<string, list<string>>}
     */
    private function refusal(SyncMutation $row, User $user): ?array
    {
        $fields = $this->catalogue->knows($row->type) ? Validator::make($row->payload, $this->catalogue->rules($row->type, $row->payload)) : null;

        return match (true) {
            $fields === null => [SyncMutation::REJECTED, 'unknown_type', "There is no quick action called {$row->type}.", null],
            ! $user->is_active || ! $user->can($this->catalogue->permission($row->type, $row->payload)) => [SyncMutation::REJECTED, 'forbidden', 'You are not allowed to do this.', null],
            $fields->fails() => [SyncMutation::REJECTED, 'invalid_payload', 'Some fields are missing or wrong.', $fields->errors()->toArray()],
            default => null,
        };
    }

    /**
     * Runs the quick action and says how it went.
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: null}
     */
    private function execute(SyncMutation $row, User $user): array
    {
        $payload = Validator::make($row->payload, $this->catalogue->rules($row->type, $row->payload))->validated();

        try {
            [$serverType, $serverId] = $this->quickEntry->run($row->type, $payload, $user, $row->occurred_at, $row->client_id);
            $row->fill(['server_type' => $serverType, 'server_id' => $serverId, 'synced_at' => now()]);
            $outcome = [SyncMutation::ACCEPTED, null, null, null];
        } catch (StaleData $e) {
            $outcome = [SyncMutation::CONFLICT, $e->errorCode(), $e->getMessage(), null];
        } catch (DomainException $e) {
            $outcome = [in_array($e->errorCode(), self::CONFLICT_CODES, true) ? SyncMutation::CONFLICT : SyncMutation::REJECTED, $e->errorCode(), $e->getMessage(), null];
        } catch (Throwable $e) {
            report($e);
            $outcome = [SyncMutation::FAILED, 'server_error', 'The server could not record this. Nothing was saved; try again.', null];
        }

        return $outcome;
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
