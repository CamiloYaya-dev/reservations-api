<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Problem;
use App\ReservationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservationRequestTest extends TestCase
{
    public function testAcceptsValidRequestWithoutNormalizingItsKey(): void
    {
        $request = ReservationRequest::fromJson('{"request_id":"Req-1","product_id":1,"quantity":3}');
        self::assertSame('Req-1', $request->requestId);
        self::assertSame(1, $request->productId);
        self::assertSame(3, $request->quantity);
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInputs(string $json, int $status): void
    {
        try {
            ReservationRequest::fromJson($json);
            self::fail('Se esperaba un error de validacion.');
        } catch (Problem $error) {
            self::assertSame($status, $error->status);
        }
    }

    public static function invalidInputs(): iterable
    {
        yield 'malformed JSON' => ['{', 400];
        yield 'array' => ['[]', 422];
        yield 'missing fields' => ['{}', 422];
        $valid = ['request_id' => 'REQ-1', 'product_id' => 1, 'quantity' => 1];
        foreach ([0, -1, 1.5, true, '1', null, 2147483648] as $i => $value) {
            yield 'quantity-' . $i => [json_encode(array_replace($valid, ['quantity' => $value]), JSON_THROW_ON_ERROR), 422];
        }
        foreach (['', ' key', 'key ', "key\n", 'á', str_repeat('a', 65), 1] as $i => $value) {
            yield 'key-' . $i => [json_encode(array_replace($valid, ['request_id' => $value]), JSON_THROW_ON_ERROR), 422];
        }
        yield 'invalid product' => ['{"request_id":"x","product_id":0,"quantity":1}', 422];
        yield 'unknown field' => ['{"request_id":"x","product_id":1,"quantity":1,"price":0}', 422];
    }
}
