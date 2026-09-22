<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Connection;
use RuntimeException;

final class ConcurrentRequests
{
    /** Proves both HTTP requests are inside MySQL waiting for product locks before releasing them. */
    public static function run(array $requests): array
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'inventory_test' || !getenv('TEST_BASE_URL')) {
            throw new RuntimeException('La prueba requiere inventory_test y TEST_BASE_URL.');
        }
        $control = Connection::open();
        $observer = Connection::open();
        $workers = [];
        try {
            $ids = array_values(array_unique(array_column($requests, 'product_id')));
            sort($ids);
            $control->beginTransaction();
            foreach ($ids as $id) {
                $lock = $control->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
                $lock->execute([$id]);
                $lock->fetchAll();
            }
            foreach ($requests as $request) {
                $command = [PHP_BINARY, __DIR__ . '/http-worker.php', base64_encode(json_encode($request, JSON_THROW_ON_ERROR))];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (!is_resource($process)) { throw new RuntimeException('No se pudo iniciar un worker.'); }
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                fwrite($pipes[0], "go\n");
                fclose($pipes[0]);
            }
            $sql = "SELECT COUNT(DISTINCT w.REQUESTING_ENGINE_TRANSACTION_ID)
                    FROM performance_schema.data_lock_waits w
                    JOIN performance_schema.data_locks l
                      ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE = w.ENGINE
                    WHERE l.OBJECT_SCHEMA = 'inventory_test' AND l.OBJECT_NAME = 'products'";
            // The application's lock timeout is 3 s. Both must reach the barrier before 2.5 s.
            $deadline = microtime(true) + 2.5;
            do {
                $waiting = (int) $observer->query($sql)->fetchColumn();
                if ($waiting >= count($requests)) { break; }
                usleep(10000);
            } while (microtime(true) < $deadline);
            if ($waiting < count($requests)) {
                throw new RuntimeException('No se observaron ambos requests esperando en InnoDB. Verifique workers HTTP y permisos de performance_schema.');
            }
            $control->commit();
            $results = [];
            foreach ($workers as $index => [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                unset($workers[$index]);
                if ($code !== 0) { throw new RuntimeException('Worker fallo: ' . $errors); }
                $results[] = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if ($control->inTransaction()) { $control->rollBack(); }
            foreach ($workers as [$process, $pipes]) {
                proc_terminate($process);
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                proc_close($process);
            }
        }
    }
}
