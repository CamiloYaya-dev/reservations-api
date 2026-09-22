<?php
declare(strict_types=1);

namespace App;

use Closure;

final class Api
{
    /** @param Closure(ReservationRequest): ReservationResult $reserve */
    public function __construct(private readonly Closure $reserve, private readonly Closure $health) {}

    /** @return array{int, array, array} status, body, headers */
    public function handle(string $method, string $path, string $contentType, string $body): array
    {
        try {
            if ($path === '/health') {
                if ($method !== 'GET') {
                    return [405, ['error' => ['code' => 'method_not_allowed', 'message' => 'Use GET.']], ['Allow' => 'GET']];
                }
                ($this->health)();
                return [200, ['status' => 'ok'], []];
            }
            if ($path !== '/reservations') {
                throw new Problem(404, 'route_not_found', 'Ruta inexistente.');
            }
            if ($method !== 'POST') {
                return [405, ['error' => ['code' => 'method_not_allowed', 'message' => 'Use POST.']], ['Allow' => 'POST']];
            }
            if (strtolower(trim(explode(';', $contentType)[0])) !== 'application/json') {
                throw new Problem(415, 'unsupported_media_type', 'Use Content-Type: application/json.');
            }
            if (strlen($body) > 4096) {
                throw new Problem(413, 'payload_too_large', 'El cuerpo excede 4096 bytes.');
            }
            $result = ($this->reserve)(ReservationRequest::fromJson($body));
            return [$result->replayed ? 200 : 201, $result->toArray(), [
                'Idempotency-Replayed' => $result->replayed ? 'true' : 'false',
            ]];
        } catch (Problem $error) {
            return [$error->status, ['error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]],
                $error->status === 503 ? ['Retry-After' => '1'] : []];
        }
    }
}
