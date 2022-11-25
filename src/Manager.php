<?php

declare(strict_types=1);

namespace Eva\Database;

class Manager
{
    public function __construct(protected ConnectionInterface $connection) {}

    public function select(string $sql, null|array $params = null): array
    {
        $stmt = $this->connection->prepare($sql, $params);
        $stmt->execute();
        $result = [];

        while ($row = $stmt->fetch()) {
            $result[$row['id']] = $row;
        }

        $stmt->closeCursor();

        return $result;
    }

    public function selectOne(string $sql, null|array $params = null): null|array
    {
        $rows = $this->select($sql, $params);

        return $rows === [] ? null : current($rows);
    }

    public function selectYield(string $sql, null|array $params = null): null|\Generator
    {
        $stmt = $this->connection->prepare($sql, $params);
        $stmt->execute();
        $row = $stmt->fetch();

        if (false === $row) {
            return null;
        }

        while ($row = $stmt->fetch()) {
            yield $row['id'] => $row;
        }

        $stmt->closeCursor();
    }

    public function insert(string $table, array $values): string
    {
        $columns = array_keys($values);
        $params = array_map(static fn ($val) => ':' . $val, $columns);
        $columns = implode(', ', $columns);
        $params = implode(', ', $params);
        $stmt = $this->connection->prepare("
            insert into $table ($columns) values ($params)
        ");

        $stmt->execute($values);
        $stmt->closeCursor();

        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $values, array $whereList): void
    {
        $columns = array_keys($values);
        $whereColumns = array_keys($whereList);
        $params = array_map(static fn ($item) => ':set_' . $item, $columns);
        $paramsWhere = array_map(static fn ($item) => ':where_' . $item, $whereColumns);
        $values = array_combine($params, array_values($values)) + array_combine($paramsWhere, array_values($whereList));
        $setters = '';
        $whereQuery = '1=1';

        foreach ($columns as $key => $column) {
            $setters .= "$column = $params[$key]";
        }

        foreach ($whereColumns as $key => $column) {
            $whereQuery .= " and $column = $paramsWhere[$key]";
        }

        $stmt = $this->connection->prepare("
            update $table set $setters where $whereQuery
        ");
        $stmt->execute($values);
        $stmt->closeCursor();
    }

    public function delete(string $table, array $whereList): void
    {
        $whereQuery = '';
        $whereColumns = array_keys($whereList);
        $params = array_map(static fn ($item) => ':' . $item, $whereColumns);
        $values = array_combine($params, array_values($whereList));

        foreach ($whereColumns as $key => $column) {
            $whereQuery .= " and $column = $params[$key]";
        }

        $stmt = $this->connection->prepare(" delete from $table where 1=1 $whereQuery", $values);
        $stmt->execute();
        $stmt->closeCursor();
    }
}
