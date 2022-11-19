<?php

declare(strict_types=1);

namespace Eva\Database\Migrations;

use Eva\Database\ConnectionInterface;

abstract class AbstractionMigration
{
    private array $sqlList = [];
    private ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    protected function addSql(string $sql): void
    {
        $this->sqlList[] = $sql;
    }

    public function execute(): void
    {
        foreach ($this->sqlList as $sql) {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $stmt->closeCursor();
        }
    }
}
