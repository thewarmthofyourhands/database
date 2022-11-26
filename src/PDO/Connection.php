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

    public function prepare(string $sql, null|array $parameters = null, array $options = []): Statement
    {
        if (null !== $parameters) {
            $listParameters = [];

            foreach ($parameters as $parameterName => $parameterValue) {
                if (true === is_array($parameterValue)) {
                    $newSqlParameterNameList = [];

                    foreach ($parameterValue as $key => $item) {
                        $newParameterName = $parameterName . '_' . $key;
                        $newSqlParameterNameList[] = ':'.$newParameterName;
                        $listParameters[$parameterName . '_' . $key] = $item;
                    }

                    $sql = str_replace(':' . $parameterName, implode(', ', $newSqlParameterNameList), $sql);
                    unset($parameters[$parameterName]);
                }
            }

            $parameters = array_merge($parameters, $listParameters);
            $stmt = new Statement($this->pdo->prepare($sql, $options));

            foreach ($parameters as $parameterName => $parameterValue) {
                $stmt->bindParam(':' . $parameterName, $parameterValue);
            }
        } else {
            $stmt = new Statement($this->pdo->prepare($sql, $options));
        }

        return $stmt;
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
}
