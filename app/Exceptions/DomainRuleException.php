<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Regla de negocio rechazada: código estable para el cliente (p. ej. AUTOASIGNACION), su estado HTTP y,
 * si ayuda a decidir qué hacer, datos extra (p. ej. los posibles duplicados de un paciente).
 */
class DomainRuleException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details ?: null,
        ], fn ($v) => $v !== null), $this->status);
    }
}
