<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violation d'une règle métier, rendue au format unifié { message, errors } (standards §4.5).
 * 422 = donnée invalide au regard d'une règle ; 409 = conflit d'état (verrou, dépendances).
 */
class RegleMetierException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, list<string>> $errors */
    public static function invalide(string $message, array $errors = [], array $extra = []): self
    {
        return new self($message, 422, $errors, $extra);
    }

    /** @param array<string, mixed> $extra */
    public static function conflit(string $message, array $extra = []): self
    {
        return new self($message, 409, [], $extra);
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage(), 'errors' => (object) $this->errors] + $this->extra,
            $this->status
        );
    }
}
