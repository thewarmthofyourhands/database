<?php

declare(strict_types=1);

namespace Eva\Database;

use RuntimeException;

class ConnectionStore implements ConnectionStoreInterface
{
    private array $store = [];

    public function add(string $alias, ConnectionInterface $connection): void
    {
        $this->store[$alias] = $connection;
    }

    public function get(string $alias = 'default'): ConnectionInterface
    {
        if (false === array_key_exists($alias, $this->store)) {
            throw new RuntimeException('Connection ' . $alias . ' is not exist');
        }

        return $this->store[$alias];
    }
}
