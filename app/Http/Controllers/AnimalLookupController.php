<?php

namespace App\Http\Controllers;

use App\Domain\Animal\Actions\LookupAnimal;
use App\Filament\Resources\Animals\AnimalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** QR / barcode / tag lookup: JSON for scanners and apps, the animal's profile page for browsers. */
class AnimalLookupController extends Controller
{
    public function __invoke(Request $request, LookupAnimal $lookup, string $code): JsonResponse|RedirectResponse
    {
        $animal = $lookup($code);

        if (! $animal) {
            abort(404, 'No animal matches that code.');
        }

        Gate::authorize('view', $animal);

        if (! $request->expectsJson()) {
            return redirect(AnimalResource::getUrl('view', ['record' => $animal]));
        }

        $animal->loadMissing(['category', 'breed', 'currentPen.building', 'currentLocation']);

        return response()->json([
            'animal_number' => $animal->animal_number,
            'public_id' => $animal->public_id,
            'sex' => $animal->sex->value,
            'category' => $animal->category->name,
            'breed' => $animal->breed?->name,
            'status' => $animal->status->value,
            'position' => $animal->positionLabel(),
            'latest_weight_kg' => $animal->latestWeight()?->weight_kg,
            'url' => AnimalResource::getUrl('view', ['record' => $animal]),
        ]);
    }
}
