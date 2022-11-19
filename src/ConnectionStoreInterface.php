<?php

declare(strict_types=1);

namespace Eva\Database;

interface ConnectionStoreInterface
{
    public function add(string $alias, ConnectionInterface $connection): void;
    public function get(string $alias = 'default'): ConnectionInterface;
}
