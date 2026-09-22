<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Connection;
use App\Problem;
use App\ReservationRequest;
use App\ReservationService;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\ConcurrentRequests;
use Tests\Support\ObservedStatement;

final class ReservationTest extends TestCase
{
    private PDO $pdo;
    private ReservationService $service;

    protected function setUp(): void
    {
        // Never delete application data, even if environment variables are misconfigured.
        if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'inventory_test') {
            self::fail('Use el servicio Docker tests: las pruebas solo permiten inventory_test y APP_ENV=test.');
        }
        $this->pdo = Connection::open();
        self::assertSame('inventory_test', $this->pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->pdo->exec('DROP TRIGGER IF EXISTS fail_reservation_insert');
        $this->pdo->exec('DELETE FROM reservations');
        $this->pdo->exec('DELETE FROM products');
        $this->service = new ReservationService($this->pdo);
    }

    private function product(int $stock): int
    {
        $query = $this->pdo->prepare('INSERT INTO products (name, stock) VALUES (?, ?)');
        $query->execute(['Producto creado solo por la prueba', $stock]);
        return (int) $this->pdo->lastInsertId();
    }

    private function request(string $key, int $id, int $quantity): ReservationRequest
    {
        return ReservationRequest::fromJson(json_encode(['request_id' => $key, 'product_id' => $id, 'quantity' => $quantity], JSON_THROW_ON_ERROR));
    }

    private function stock(int $id): int
    {
        $query = $this->pdo->prepare('SELECT stock FROM products WHERE id = ?');
        $query->execute([$id]);
        return (int) $query->fetchColumn();
    }

    private function countReservations(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
    }

    public function testSuccessfulReservation(): void
    {
        $id = $this->product(10);
        $result = $this->service->reserve($this->request('success', $id, 3));
        self::assertFalse($result->replayed);
        self::assertSame(7, $result->remainingStock);
        self::assertSame(7, $this->stock($id));
        self::assertSame(1, $this->countReservations());
    }

    public function testInsufficientStockLeavesNoReservationOrDecrement(): void
    {
        $id = $this->product(1);
        try {
            $this->service->reserve($this->request('insufficient', $id, 2));
            self::fail('Debio rechazar la reserva.');
        } catch (Problem $error) {
            self::assertSame('insufficient_stock', $error->errorCode);
        }
        self::assertSame(1, $this->stock($id));
        self::assertSame(0, $this->countReservations());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testReplayReturnsOriginalBodyEvenAfterStockChanges(): void
    {
        $id = $this->product(3);
        $request = $this->request('replay', $id, 1);
        $first = $this->service->reserve($request);
        $this->service->reserve($this->request('exhaust', $id, 2));
        $replay = $this->service->reserve($request);
        self::assertTrue($replay->replayed);
        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame(0, $this->stock($id));
        self::assertSame(2, $this->countReservations());
    }

    public function testSameKeyWithDifferentPayloadIsAConflict(): void
    {
        $id = $this->product(4);
        $this->service->reserve($this->request('conflict', $id, 1));
        try {
            $this->service->reserve($this->request('conflict', $id, 2));
            self::fail('Debio detectar el cambio de payload.');
        } catch (Problem $error) {
            self::assertSame('idempotency_conflict', $error->errorCode);
        }
        self::assertSame(3, $this->stock($id));
        self::assertSame(1, $this->countReservations());
    }

    public function testProductMustExist(): void
    {
        $this->expectException(Problem::class);
        $this->expectExceptionMessage('El producto no existe.');
        $this->service->reserve($this->request('missing', 2147483647, 1));
    }

    public function testInsertFailureRollsBackTheStockDecrement(): void
    {
        $id = $this->product(2);
        $this->pdo->exec("CREATE TRIGGER fail_reservation_insert BEFORE INSERT ON reservations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected failure'");
        try {
            $this->service->reserve($this->request('rollback', $id, 1));
            self::fail('Debio fallar la insercion.');
        } catch (PDOException $error) {
            self::assertSame('45000', $error->getCode());
        } finally {
            $this->pdo->exec('DROP TRIGGER fail_reservation_insert');
        }
        self::assertSame(2, $this->stock($id));
        self::assertSame(0, $this->countReservations());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testDatabaseRejectsNegativeStockAndNonpositiveQuantity(): void
    {
        $id = $this->product(1);
        foreach ([
            "UPDATE products SET stock = -1 WHERE id = $id",
            "INSERT INTO reservations (request_id, product_id, quantity, remaining_stock) VALUES ('invalid', $id, 0, 1)",
        ] as $sql) {
            try { $this->pdo->exec($sql); self::fail('CHECK no aplicado.'); }
            catch (PDOException $error) { self::assertSame(3819, (int) $error->errorInfo[1]); }
        }
        self::assertSame(1, $this->stock($id));
    }

    public function testLockTimeoutIsBoundedAndLeavesTheConnectionUsable(): void
    {
        $id = $this->product(1);
        $blocker = Connection::open();
        $blocker->beginTransaction();
        $blocker->query('SELECT id FROM products WHERE id = ' . $id . ' FOR UPDATE')->fetchAll();
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $this->service->reserve($this->request('timeout', $id, 1));
            self::fail('Debio agotarse el numero acotado de reintentos.');
        } catch (Problem $error) {
            self::assertSame(503, $error->status);
            self::assertSame('temporarily_unavailable', $error->errorCode);
            self::assertFalse($this->pdo->inTransaction());
        } finally {
            $blocker->rollBack();
        }
        self::assertSame(1, $this->stock($id));
        self::assertSame(0, $this->countReservations());
        self::assertFalse($this->service->reserve($this->request('timeout', $id, 1))->replayed);
        self::assertSame(0, $this->stock($id));
    }

    public function testForeignKeyAndUniqueKeyAreEnforcedByMysql(): void
    {
        $id = $this->product(2);
        $this->service->reserve($this->request('unique', $id, 1));
        foreach ([
            ["INSERT INTO reservations (request_id, product_id, quantity, remaining_stock) VALUES ('unique', $id, 1, 0)", 1062],
            ["INSERT INTO reservations (request_id, product_id, quantity, remaining_stock) VALUES ('fk', 2147483647, 1, 0)", 1452],
        ] as [$sql, $code]) {
            try { $this->pdo->exec($sql); self::fail('Restriccion no aplicada.'); }
            catch (PDOException $error) { self::assertSame($code, (int) $error->errorInfo[1]); }
        }
    }

    public function testConcurrentDifferentRequestsCannotOversell(): void
    {
        $id = $this->product(1);
        $results = ConcurrentRequests::run([
            ['request_id' => 'concurrent-A', 'product_id' => $id, 'quantity' => 1],
            ['request_id' => 'concurrent-B', 'product_id' => $id, 'quantity' => 1],
        ]);
        $codes = array_column($results, 'status'); sort($codes);
        self::assertSame([201, 409], $codes);
        $rejected = array_values(array_filter($results, static fn ($r) => $r['status'] === 409))[0];
        self::assertSame('insufficient_stock', $rejected['body']['error']['code']);
        self::assertSame(0, $this->stock($id));
        self::assertSame(1, $this->countReservations());
    }

    public function testConcurrentIdenticalRequestsDiscountOnlyOnce(): void
    {
        $id = $this->product(1);
        $request = ['request_id' => 'concurrent-same', 'product_id' => $id, 'quantity' => 1];
        $results = ConcurrentRequests::run([$request, $request]);
        $codes = array_column($results, 'status'); sort($codes);
        self::assertSame([200, 201], $codes);
        self::assertSame($results[0]['body'], $results[1]['body']);
        self::assertSame(0, $this->stock($id));
        self::assertSame(1, $this->countReservations());
    }

    public function testConcurrentSameKeyForDifferentProductsHasOnlyOneWinner(): void
    {
        $one = $this->product(1); $two = $this->product(1);
        $results = ConcurrentRequests::run([
            ['request_id' => 'global-key', 'product_id' => $one, 'quantity' => 1],
            ['request_id' => 'global-key', 'product_id' => $two, 'quantity' => 1],
        ]);
        $codes = array_column($results, 'status'); sort($codes);
        self::assertSame([201, 409], $codes);
        $rejected = array_values(array_filter($results, static fn ($r) => $r['status'] === 409))[0];
        self::assertSame('idempotency_conflict', $rejected['body']['error']['code']);
        self::assertSame(1, $this->stock($one) + $this->stock($two));
        self::assertSame(1, $this->countReservations());
    }

    public function testDuplicate1062RecoveryRollsBackBeforeReadingTheWinner(): void
    {
        $loser = $this->product(1);
        $winner = $this->product(1);
        $codes = [];
        $armed = true;
        $before = function (string $sql) use (&$armed, $winner): void {
            if ($armed && str_starts_with($sql, 'INSERT INTO reservations')) {
                $armed = false;
                // The first transaction has already checked the key and deducted stock.
                // Commit the same key on another product before its INSERT reaches MySQL.
                (new ReservationService(Connection::open()))->reserve($this->request('forced-1062', $winner, 1));
            }
        };
        $onError = static function (int $code) use (&$codes): void { $codes[] = $code; };
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ObservedStatement::class, [$before, $onError]]);
        try {
            $this->service->reserve($this->request('forced-1062', $loser, 1));
            self::fail('Expected idempotency conflict');
        } catch (Problem $error) {
            self::assertSame('idempotency_conflict', $error->errorCode);
        } finally {
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
        }
        self::assertSame([1062], $codes, 'Must actually execute the 1062 recovery branch.');
        self::assertFalse($this->pdo->inTransaction());
        self::assertSame(1, $this->stock($loser));
        self::assertSame(0, $this->stock($winner));
        self::assertSame(1, $this->countReservations());
    }

    public function testRealDeadlock1213RetriesTheWholeTransactionSuccessfully(): void
    {
        $target = $this->product(1);
        $guard = $this->product(1);
        $padding = [];
        for ($i = 0; $i < 24; $i++) { $padding[] = $this->product(1); }
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/Support/deadlock-worker.php',
            json_encode(compact('target', 'guard', 'padding'), JSON_THROW_ON_ERROR)],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $codes = [];
        $armed = true;
        try {
            stream_set_timeout($pipes[1], 10);
            self::assertSame("ready\n", fgets($pipes[1]));
            $before = function (string $sql) use (&$armed, $guard, $pipes): void {
                if ($armed && str_starts_with($sql, 'SELECT stock FROM products')) {
                    $armed = false;
                    $this->pdo->query('SELECT id FROM products WHERE id = ' . $guard . ' FOR UPDATE')->fetchAll();
                    fwrite($pipes[0], "go\n");
                    fflush($pipes[0]);
                    // Worker holds target and requests guard; service holds guard and requests target.
                }
            };
            $onError = static function (int $code) use (&$codes): void { $codes[] = $code; };
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ObservedStatement::class, [$before, $onError]]);
            $result = $this->service->reserve($this->request('real-deadlock', $target, 1));
            self::assertSame("done\n", fgets($pipes[1]));
            self::assertSame([1213], $codes, 'Must observe a real MySQL deadlock on the service connection.');
            self::assertFalse($result->replayed);
            self::assertSame(0, $result->remainingStock);
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(0, $this->stock($target));
            self::assertSame(1, $this->stock($guard));
            self::assertSame(1, $this->countReservations());
            foreach ($pipes as $pipe) { fclose($pipe); }
            self::assertSame(0, proc_close($process));
        } finally {
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                proc_close($process);
            }
        }
    }
}
