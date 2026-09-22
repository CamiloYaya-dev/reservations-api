<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Connection
{
    public static function open(): PDO
    {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'inventory';
        $user = getenv('DB_USER') ?: 'reservation_app';
        $password = getenv('DB_PASSWORD');
        if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/D', $host)
            || !ctype_digit($port)
            || !preg_match('/\A[a-zA-Z0-9_]+\z/D', $name)
            || $password === false || $password === '') {
            throw new \RuntimeException('Configuracion de base de datos incompleta o invalida.');
        }
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
}
