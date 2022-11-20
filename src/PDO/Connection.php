<?php

declare(strict_types=1);

namespace Eva\Database\PDO;

use Eva\Database\ConnectionInterface;
use Eva\Database\LevelTransactionEnum;
use PDO;

class Connection implements ConnectionInterface
{
    protected readonly PDO $pdo;
    protected readonly string $databaseName;

    public function __construct(
        string $host,
        string $port,
        string $databaseName,
        string $username,
        string $password,
    ) {
        $this->databaseName = $databaseName;
        $dsn = "mysql:host=$host;port=$port;dbname=$databaseName";
        $this->pdo = new PDO($dsn, $username, $password);
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function setLevelTransaction(LevelTransactionEnum $levelTransaction): void
    {
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL ' . $levelTransaction->value . ';');
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function prepare(string $sql, array $options = []): Statement
    {
        return new Statement($this->pdo->prepare($sql, $options));
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function getNativeConnection(): PDO
    {
        return $this->pdo;
    }

    public function quote(string $param): string
    {
        return $this->pdo->quote($param);
    }

    public function prepareListParam(string|array $param): string
    {
        if (true === is_string($param)) {
            return $this->quote($param);
        }

        foreach ($param as &$item) {
            $item = $this->quote((string) $item);
        }

        return implode(', ', $param);
    }
}
