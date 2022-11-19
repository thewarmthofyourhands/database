<?php

declare(strict_types=1);

namespace Eva\Database\PDO;

use Eva\Database\StatementInterface;
use PDO;
use PDOStatement;

class Statement implements StatementInterface
{
    protected readonly PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function bindParam(int|string $key, mixed $value, int $type = PDO::PARAM_STR): void
    {
        $this->statement->bindParam($key, $value, $type);
    }

    public function bindColumn(int|string $key, mixed $value, int $type = PDO::PARAM_STR): void
    {
        $this->statement->bindColumn($key, $value, $type);
    }

    public function closeCursor(): void
    {
        $this->statement->closeCursor();
    }

    public function execute(null|array $params = null): void
    {
        $this->statement->execute($params);
    }

    public function fetch(int $mode = PDO::FETCH_BOTH, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function getNativeStatement(): PDOStatement
    {
        return $this->statement;
    }
}
