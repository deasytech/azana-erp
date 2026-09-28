<?php

namespace App\Http\Controllers;

use App\Domain\System\Actions\RunHealthChecks;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(RunHealthChecks $checks): JsonResponse
    {
        $results = collect($checks());
        $healthy = $results->every(fn ($r) => $r->ok);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $results->mapWithKeys(fn ($r) => [
                $r->name => ['ok' => $r->ok, 'detail' => $r->detail],
            ]),
        ], $healthy ? 200 : 503);
    }
}
