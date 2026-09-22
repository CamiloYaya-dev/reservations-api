<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\ErrorResponse;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ErrorResponseTest extends TestCase
{
    public function testClassifiesErrorsWithoutLeakingSensitiveDetails(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'error-response-');
        self::assertNotFalse($log);
        $original = ini_set('error_log', $log);
        try {
            $cases = [[new \LogicException('private-message-password-SQL'), 500]];
            foreach ([[1045, 500], [1049, 500], [1064, 500], [1040, 503], [2002, 503], [2006, 503], [2013, 503]] as [$code, $status]) {
                $error = new PDOException('private-message-password-SQL');
                $error->errorInfo = ['HY000', $code, 'private-message-password-SQL'];
                $cases[] = [$error, $status];
            }
            foreach ($cases as [$error, $expected]) {
                [$status, $body, $headers] = ErrorResponse::fromThrowable($error);
                self::assertSame($expected, $status);
                self::assertSame($expected === 503 ? ['Retry-After' => '1'] : [], $headers);
                self::assertSame($expected === 503 ? 'service_unavailable' : 'internal_error', $body['error']['code']);
                self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $body['error']['reference']);
                self::assertStringNotContainsString('private-message', json_encode($body));
            }
            $contents = file_get_contents($log);
            self::assertStringNotContainsString('private-message', $contents);
            self::assertStringContainsString('"mysql_code":1045', $contents);
            self::assertStringContainsString('"sqlstate":"HY000"', $contents);
        } finally {
            ini_set('error_log', $original);
            unlink($log);
        }
    }
}
