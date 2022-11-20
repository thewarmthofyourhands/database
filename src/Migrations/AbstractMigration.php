<?php

declare(strict_types=1);

namespace Eva\Database\Migrations;

use Eva\Database\ConnectionInterface;

abstract class AbstractMigration
{
    private ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function execute(string $sql): void
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }
}
