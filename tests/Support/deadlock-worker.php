<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'inventory_test') { exit(2); }
$pdo = App\Connection::open();
try {
    $ids = json_decode($argv[1], true, 16, JSON_THROW_ON_ERROR);
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
    $lock->execute([$ids['target']]);
    $lock->fetchAll();
    // A heavier transaction makes the service's transaction the deadlock victim.
    $update = $pdo->prepare('UPDATE products SET stock = stock + 1 WHERE id = ?');
    foreach ($ids['padding'] as $id) { $update->execute([$id]); }
    fwrite(STDOUT, "ready\n");
    fflush(STDOUT);
    if (fgets(STDIN) !== "go\n") { throw new RuntimeException('Missing barrier signal'); }
    $lock->execute([$ids['guard']]);
    $lock->fetchAll();
    $pdo->commit();
    fwrite(STDOUT, "done\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, get_class($error) . ': ' . $error->getCode());
    exit(1);
}
