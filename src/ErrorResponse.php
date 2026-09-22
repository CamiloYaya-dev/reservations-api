<?php
declare(strict_types=1);

namespace App;

use PDOException;
use Throwable;

final class ErrorResponse
{
    /** @return array{int, array, array} */
    public static function fromThrowable(Throwable $error): array
    {
        $reference = bin2hex(random_bytes(8));
        $state = $error instanceof PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : '';
        $state = preg_match('/\A[A-Z0-9]{5}\z/D', $state) === 1 ? $state : '';
        $mysqlCode = $error instanceof PDOException ? (int) ($error->errorInfo[1] ?? 0) : 0;
        $temporary = $error instanceof PDOException && (
            str_starts_with($state, '08')
            || in_array($mysqlCode, [1040, 1203, 1205, 1213, 2002, 2003, 2006, 2013], true)
        );
        // Only structured diagnostics: never exception messages, SQL, bodies or credentials.
        error_log(json_encode([
            'reference' => $reference,
            'exception' => get_class($error),
            'sqlstate' => $state ?: null,
            'mysql_code' => $mysqlCode ?: null,
        ], JSON_THROW_ON_ERROR));
        return [
            $temporary ? 503 : 500,
            ['error' => [
                'code' => $temporary ? 'service_unavailable' : 'internal_error',
                'message' => $temporary
                    ? 'Servicio temporalmente no disponible. Reintente con el mismo request_id.'
                    : 'Error interno. Contacte al responsable con la referencia indicada.',
                'reference' => $reference,
            ]],
            $temporary ? ['Retry-After' => '1'] : [],
        ];
    }
}
