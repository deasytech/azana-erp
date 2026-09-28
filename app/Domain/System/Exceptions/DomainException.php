<?php

namespace App\Domain\System\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base class for business-rule violations raised by domain actions.
 *
 * Rendered as a 422 JSON response so Filament, Livewire, API and offline
 * sync clients all receive the same failure shape.
 */
class DomainException extends RuntimeException
{
    public function __construct(string $message, protected string $errorCode = 'domain_error')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 422);
    }
}
