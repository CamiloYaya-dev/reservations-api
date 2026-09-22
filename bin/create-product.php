<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || $argc !== 3 || trim($argv[1]) === ''
    || !mb_check_encoding($argv[1], 'UTF-8') || mb_strlen($argv[1], 'UTF-8') > 150
    || !ctype_digit($argv[2]) || strlen($argv[2]) > 10 || (int) $argv[2] > 2147483647) {
    fwrite(STDERR, "Uso: php bin/create-product.php \"Nombre del producto\" STOCK_ENTERO_NO_NEGATIVO\n");
    exit(1);
}
try {
    $pdo = App\Connection::open();
    $statement = $pdo->prepare('INSERT INTO products (name, stock) VALUES (?, ?)');
    $statement->execute([trim($argv[1]), (int) $argv[2]]);
    echo json_encode(['product_id' => (int) $pdo->lastInsertId(), 'stock' => (int) $argv[2]], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable) {
    fwrite(STDERR, "No se pudo crear el producto. Compruebe conexion, permisos y esquema.\n");
    exit(1);
}
