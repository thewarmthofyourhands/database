<?php

declare(strict_types=1);

namespace Eva\Database;

interface StatementInterface
{
    public function bindParam(int|string $key, mixed $value, int $type): void;
    public function bindColumn(int|string $key, mixed $value, int $type): void;
    public function closeCursor(): void;
    public function execute(null|array $params): void;
    public function fetch(int $mode, int $cursorOrientation, int $cursorOffset): mixed;
}
