<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Mobile\Actions\ProcessMutation;
use App\Domain\Mobile\Models\SyncMutation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The offline sync contract: devices queue mutations while offline and push them in order when they can. See docs/API.md. */
class SyncController extends Controller
{
    private const MAX_BATCH = 100;

    /** Pushes a queue of mutations. They are processed in the order sent; each gets its own outcome, so one failure never blocks the rest. */
    public function push(Request $request, ProcessMutation $process): JsonResponse
    {
        $data = $request->validate(['device_id' => 'required|string|max:64', 'mutations' => 'required|array|min:1|max:'.self::MAX_BATCH, 'mutations.*' => 'array']);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'results' => array_map(fn (array $m) => $process($request->user(), $data['device_id'], $m), $data['mutations']),
        ]);
    }

    /** One quick action, sent on its own (same contract and same outcomes as one entry of a push). */
    public function quick(Request $request, ProcessMutation $process, string $type): JsonResponse
    {
        $data = $request->validate(['device_id' => 'required|string|max:64', 'client_id' => 'required|uuid', 'occurred_at' => 'required|date', 'payload' => 'required|array']);
        $result = $process($request->user(), $data['device_id'], ['type' => $type] + $data);

        return response()->json($result, match ($result['status']) {
            SyncMutation::ACCEPTED => $result['replayed'] ? 200 : 201, SyncMutation::CONFLICT => 409, SyncMutation::FAILED => 503, default => 422,
        });
    }

    /** How this device's mutations stand: counts, the last successful sync, and what needs attention. */
    public function status(Request $request): JsonResponse
    {
        $device = $request->validate(['device_id' => 'required|string|max:64'])['device_id'];
        $mine = SyncMutation::where('user_id', $request->user()->id)->where('device_id', $device);

        return response()->json([
            'device_id' => $device,
            'counts' => array_replace(['accepted' => 0, 'rejected' => 0, 'conflict' => 0, 'failed' => 0], (clone $mine)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all()),
            'last_synced_at' => (clone $mine)->where('status', SyncMutation::ACCEPTED)->max('synced_at'),
            'needs_attention' => (clone $mine)->where('status', '!=', SyncMutation::ACCEPTED)->orderByDesc('id')->limit(50)->get()
                ->map(fn (SyncMutation $m) => ['client_id' => $m->client_id, 'type' => $m->type, 'status' => $m->status, 'code' => $m->error_code, 'message' => $m->error_message, 'reviewed' => $m->reviewed_at !== null])->all(),
        ]);
    }

    /** The stored outcome of one mutation. */
    public function show(Request $request, string $clientId): JsonResponse
    {
        $m = SyncMutation::where('client_id', $clientId)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'client_id' => $m->client_id, 'type' => $m->type, 'status' => $m->status, 'server_type' => $m->server_type, 'server_id' => $m->server_id, 'attempts' => $m->attempts,
            'synced_at' => $m->synced_at?->toIso8601String(), 'error' => $m->status === SyncMutation::ACCEPTED ? null : ['code' => $m->error_code, 'message' => $m->error_message, 'fields' => $m->errors],
        ]);
    }
}
