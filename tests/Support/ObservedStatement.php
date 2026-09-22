<?php
declare(strict_types=1);

namespace Tests\Support;

use Closure;
use PDOException;
use PDOStatement;

// Test-only instrumentation. Every SQL statement still executes against real MySQL.
final class ObservedStatement extends PDOStatement
{
    protected function __construct(private Closure $before, private Closure $onError) {}

    public function execute(?array $params = null): bool
    {
        ($this->before)($this->queryString);
        try {
            return parent::execute($params);
        } catch (PDOException $error) {
            ($this->onError)((int) ($error->errorInfo[1] ?? 0));
            throw $error;
        }
    }
}
