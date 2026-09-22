<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Api;
use App\Problem;
use App\ReservationRequest;
use App\ReservationResult;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    public function testInvalidRequestsNeverCallTheReservationService(): void
    {
        $api = new Api(static function (): never { self::fail('No debe abrirse una transaccion.'); }, static fn () => null);
        foreach ([
            ['GET', '/reservations', '', '', 405],
            ['POST', '/missing', 'application/json', '{}', 404],
            ['POST', '/reservations', 'text/plain', '{}', 415],
            ['POST', '/reservations', 'application/json', str_repeat('x', 4097), 413],
            ['POST', '/reservations', 'application/json', '{', 400],
            ['POST', '/reservations', 'application/json', '{}', 422],
        ] as [$method, $path, $type, $body, $status]) {
            self::assertSame($status, $api->handle($method, $path, $type, $body)[0]);
        }
    }

    public function testCreationAndReplayHaveTheSameBodyAndDistinctStatus(): void
    {
        $json = '{"request_id":"r","product_id":1,"quantity":1}';
        $responses = [];
        foreach ([false, true] as $replayed) {
            $api = new Api(static fn (ReservationRequest $r) => new ReservationResult(7, 0, $replayed), static fn () => null);
            $responses[] = $api->handle('POST', '/reservations', 'application/json; charset=utf-8', $json);
        }
        self::assertSame(201, $responses[0][0]);
        self::assertSame(200, $responses[1][0]);
        self::assertSame($responses[0][1], $responses[1][1]);
        self::assertSame('true', $responses[1][2]['Idempotency-Replayed']);
    }

    public function testBusinessErrorIsMappedToAStableHttpError(): void
    {
        $api = new Api(static fn () => throw new Problem(409, 'insufficient_stock', 'Inventario insuficiente.'), static fn () => null);
        $response = $api->handle('POST', '/reservations', 'application/json', '{"request_id":"r","product_id":1,"quantity":1}');
        self::assertSame(409, $response[0]);
        self::assertSame('insufficient_stock', $response[1]['error']['code']);
    }
}
