<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Mobile\Actions\GetReferenceData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    /** The lists to keep offline. Send ?version= with the version you hold to learn whether anything changed. */
    public function __invoke(Request $request, GetReferenceData $reference): JsonResponse
    {
        return response()->json($reference($request->user(), $request->query('version')));
    }
}
