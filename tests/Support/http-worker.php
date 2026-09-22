<?php
declare(strict_types=1);

// A separate OS process for each HTTP request. Parent controls the start barrier.
if (fgets(STDIN) !== "go\n") { exit(2); }
try {
    $payload = base64_decode($argv[1], true);
    if ($payload === false) { throw new RuntimeException('Invalid payload'); }
    $url = rtrim(getenv('TEST_BASE_URL') ?: '', '/') . '/reservations';
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nConnection: close\r\n",
        'content' => $payload,
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $body = file_get_contents($url, false, $context);
    if ($body === false || !isset($http_response_header[0])) { throw new RuntimeException('HTTP failed'); }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    echo json_encode(['status' => (int) ($match[1] ?? 0), 'body' => json_decode($body, true, 16, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage());
    exit(1);
}
