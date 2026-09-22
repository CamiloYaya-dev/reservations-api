<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
if (PHP_SAPI !== 'cli' || $argc !== 2 || !ctype_digit($argv[1]) || (int) $argv[1] < 1 || (int) $argv[1] > 2147483647) {
    fwrite(STDERR, "Uso: php bin/inspect.php PRODUCT_ID\n");
    exit(1);
}
try {
    $pdo = App\Connection::open();
    $query = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ?');
    $query->execute([(int) $argv[1]]);
    $product = $query->fetch();
    if ($product === false) { fwrite(STDERR, "Producto inexistente.\n"); exit(1); }
    $query = $pdo->prepare('SELECT id, request_id, quantity, remaining_stock, created_at FROM reservations WHERE product_id = ? ORDER BY id');
    $query->execute([(int) $argv[1]]);
    echo json_encode(['product' => $product, 'reservations' => $query->fetchAll()], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable) {
    fwrite(STDERR, "No se pudo consultar el producto. Compruebe la base de datos.\n"); exit(1);
}
