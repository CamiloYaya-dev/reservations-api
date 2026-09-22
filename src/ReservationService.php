<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Throwable;

final class ReservationService
{
    public function __construct(private readonly PDO $pdo) {}

    public function reserve(ReservationRequest $request): ReservationResult
    {
        // Retry the entire transaction, never an isolated statement.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->pdo->beginTransaction();
                // Replay before checking stock (even when the original request exhausted it).
                $existing = $this->find($request->requestId);
                if ($existing !== false) {
                    $result = $this->replay($existing, $request);
                    $this->pdo->commit();
                    return $result;
                }

                $query = $this->pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $query->execute([$request->productId]);
                $product = $query->fetch();
                if ($product === false) {
                    throw new Problem(404, 'product_not_found', 'El producto no existe.');
                }

                // READ COMMITTED observes a reservation committed while waiting for the product lock.
                $existing = $this->find($request->requestId);
                if ($existing !== false) {
                    $result = $this->replay($existing, $request);
                    $this->pdo->commit();
                    return $result;
                }
                if ((int) $product['stock'] < $request->quantity) {
                    throw new Problem(409, 'insufficient_stock', 'Inventario insuficiente.');
                }

                $update = $this->pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                $update->execute([$request->quantity, $request->productId, $request->quantity]);
                if ($update->rowCount() !== 1) {
                    throw new \RuntimeException('Fallo de la invariante de inventario.');
                }
                $remaining = (int) $product['stock'] - $request->quantity;
                $insert = $this->pdo->prepare(
                    'INSERT INTO reservations (request_id, product_id, quantity, remaining_stock) VALUES (?, ?, ?, ?)'
                );
                $insert->execute([$request->requestId, $request->productId, $request->quantity, $remaining]);
                $result = new ReservationResult((int) $this->pdo->lastInsertId(), $remaining, false);
                $this->pdo->commit();
                return $result;
            } catch (Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if ($error instanceof PDOException) {
                    $mysqlCode = (int) ($error->errorInfo[1] ?? 0);
                    if ($mysqlCode === 1062) {
                        // Different product locks can still race on the global UNIQUE request_id.
                        // Rollback first: this connection must not keep its stock decrement.
                        $existing = $this->find($request->requestId);
                        if ($existing !== false) {
                            return $this->replay($existing, $request);
                        }
                    }
                    if (in_array($mysqlCode, [1205, 1213], true)) {
                        if ($attempt < 3) {
                            usleep(random_int(10000, 50000) * $attempt);
                            continue;
                        }
                        throw new Problem(503, 'temporarily_unavailable', 'Contencion temporal. Reintente con el mismo request_id.');
                    }
                }
                // Connection/commit errors are not blindly retried: outcome may be unknown.
                throw $error;
            }
        }
        throw new \LogicException('Unreachable');
    }

    private function find(string $requestId): array|false
    {
        $query = $this->pdo->prepare(
            'SELECT id, product_id, quantity, remaining_stock FROM reservations WHERE request_id = ?'
        );
        $query->execute([$requestId]);
        return $query->fetch();
    }

    private function replay(array $row, ReservationRequest $request): ReservationResult
    {
        if ((int) $row['product_id'] !== $request->productId || (int) $row['quantity'] !== $request->quantity) {
            throw new Problem(409, 'idempotency_conflict', 'request_id ya fue utilizado con otro producto o cantidad.');
        }
        return new ReservationResult((int) $row['id'], (int) $row['remaining_stock'], true);
    }
}
