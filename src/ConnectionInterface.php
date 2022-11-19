<?php

declare(strict_types=1);

namespace Eva\Database;

interface ConnectionInterface
{
    public function setLevelTransaction(LevelTransactionEnum $levelTransaction): void;
    public function beginTransaction(): void;
    public function rollback(): void;
    public function commit(): void;
    public function inTransaction(): bool;
    public function prepare(string $sql, array $options = []): StatementInterface;
    public function lastInsertId(): string;
    public function execute(string $sql): void;
    public function quote(string $param): string;
    public function prepareListParam(string|array $param): string;
    public function getNativeConnection(): object;
    public function getDatabaseName(): string;
}
