<?php
declare(strict_types=1);

use App\Api;
use App\Connection;
use App\ErrorResponse;
use App\ReservationRequest;
use App\ReservationService;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    // Lazy connection: invalid HTTP requests do not open a database transaction.
    $api = new Api(
        static fn (ReservationRequest $request) => (new ReservationService(Connection::open()))->reserve($request),
        static function (): void { Connection::open()->query('SELECT 1'); },
    );
    $input = fopen('php://input', 'rb');
    $body = $input === false ? '' : stream_get_contents($input, 4097);
    [$status, $payload, $headers] = $api->handle(
        $_SERVER['REQUEST_METHOD'] ?? '',
        parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
        $_SERVER['CONTENT_TYPE'] ?? '',
        $body === false ? '' : $body,
    );
} catch (Throwable $error) {
    [$status, $payload, $headers] = ErrorResponse::fromThrowable($error);
}
http_response_code($status);
foreach ($headers as $name => $value) {
    header($name . ': ' . $value);
}
echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
