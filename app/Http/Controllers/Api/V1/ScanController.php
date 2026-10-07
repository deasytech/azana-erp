<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Animal\Actions\LookupAnimal;
use App\Domain\Mobile\Actions\ResolveScan;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnimalSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScanController extends Controller
{
    /** What a scanned code is (animal, pen, batch, stock item, litter or task). */
    public function scan(Request $request, ResolveScan $resolve, string $code): JsonResponse
    {
        return response()->json($resolve($code, $request->user()) ?? abort(404, 'Nothing matches that code.'));
    }

    /** An animal's summary, by tag, number, public id or QR link. */
    public function animal(LookupAnimal $lookup, string $code): JsonResponse
    {
        $animal = $lookup($code) ?? abort(404, 'No animal matches that code.');
        Gate::authorize('view', $animal);

        return (new AnimalSummary($animal))->response();
    }
}
