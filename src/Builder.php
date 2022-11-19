<?php

declare(strict_types=1);

namespace Eva\Database;

class Builder
{
    protected array $sql = [
        'SELECT' => '',
        'INSERT' => '',
        'VALUES' => '',
        'UPDATE' => '',
        'DELETE' => '',
        'SET' => '',
        'FROM' => '',
        'JOIN' => '',
        'WHERE' => '',
        'GROUP_BY' => '',
        'HAVING' => '',
        'ORDER_BY' => '',
        'LIMIT' => '',
    ];
    protected array $params = [];
    protected QueryTypeEnum $type;

    public function __construct() {}

    public function select(string ...$columns): static
    {
        $this->type = QueryTypeEnum::SELECT;
        $this->sql['SELECT'] .= 'SELECT ' . implode(', ', $columns);

        return $this;
    }

    public function addSelect(string ...$columns): static
    {
        $this->sql['SELECT'] .= implode(', ', $columns);

        return $this;
    }

    public function update(string $table): static
    {
        $this->type = QueryTypeEnum::UPDATE;
        $this->sql['UPDATE'] .= "UPDATE $table";

        return $this;
    }

    public function set(string|array $values): static
    {
        if (is_string($values)) {
            $this->sql['SET'] .= " SET $values";
        } else {
            $columns = array_keys($values);
            $params = array_values($values);
            $sql = '';

            foreach ($columns as $key => $column) {
                $sql .= "$column = $params[$key], ";
            }

            $sql = substr($sql, 0, -2);
            $this->sql['SET'] .= " SET $sql";
        }

        return $this;
    }

    public function insert(string $table): static
    {
        $this->type = QueryTypeEnum::INSERT;
        $this->sql['INSERT'] .= "INSERT INTO $table";

        return $this;
    }

    public function values(string|array $values): static
    {
        if (is_string($values)) {
            $this->sql['VALUES'] .= " $values";
        } else {
            $columns = array_keys($values);
            $params = array_values($values);
//            $params = array_map(fn ($item) => ':' . $item, $columns);
            $columnsSql = '(' . implode(', ', $columns) . ')';
            $paramsSql = '(' . implode(', ', $params) . ')';
//            $this->bindParams($params);
            $this->sql['VALUES'] .=  " $columnsSql VALUES $paramsSql";
        }

        return $this;
    }

    public function delete(): static
    {
        $this->type = QueryTypeEnum::DELETE;
        $this->sql['DELETE'] .= 'DELETE';

        return $this;
    }

    public function from(string $table): static
    {
        $this->sql['FROM'] .= " FROM $table";

        return $this;
    }

    public function join(string $table, string $onExpr, string $typeJoin = 'JOIN'): static
    {
        $this->sql['JOIN'] .= " $typeJoin $table ON $onExpr";

        return $this;
    }

    public function where(string ...$expr): static
    {
        $this->sql['WHERE'] .= " WHERE 1=1";

        if (true === isset($expr[0])) {
            $this->andWhere($expr[0]);
        }

        return $this;
    }

    public function andWhere(string $expr): static
    {
        $this->sql['WHERE'] .= " AND $expr";

        return $this;
    }

    public function orWhere(string $expr): static
    {
        $this->sql['WHERE'] .= " OR $expr";

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->sql['GROUP_BY'] .= " GROUP BY " . implode(', ', $columns);

        return $this;
    }

    public function addGroupBy(string ...$columns): static
    {
        $this->sql['GROUP_BY'] .= ", " . implode(', ', $columns);

        return $this;
    }

    public function having(string $expr): static
    {
        $this->sql['HAVING'] .= " HAVING $expr";

        return $this;
    }

    public function andHaving(string $expr): static
    {
        $this->sql['HAVING'] .= " AND $expr";

        return $this;
    }

    public function orHaving(string $expr): static
    {
        $this->sql['HAVING'] .= " OR $expr";

        return $this;
    }

    public function orderBy(string ...$columns): static
    {
        $this->sql['ORDER_BY'] .= " ORDER BY " . implode(', ', $columns);

        return $this;
    }

    public function addOrderBy(string ...$columns): static
    {
        $this->sql['ORDER_BY'] .= ", " . implode(', ', $columns);

        return $this;
    }

    public function bindParams(array $params): static
    {
        $this->params += $params;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getSelectSql(): string
    {
        $queryData = $this->sql;

        return $queryData['SELECT'] .
            $queryData['FROM'] .
            $queryData['JOIN'] .
            $queryData['WHERE'] .
            $queryData['GROUP_BY'] .
            $queryData['HAVING'] .
            $queryData['ORDER_BY'] .
            $queryData['LIMIT'];
    }

    public function getInsertSql(): string
    {
        $queryData = $this->sql;

        return $queryData['INSERT'] .
            $queryData['VALUES'];
    }

    public function getUpdateSql(): string
    {
        $queryData = $this->sql;

        return $queryData['UPDATE'] .
            $queryData['SET'] .
            $queryData['WHERE'];
    }

    public function getDeleteSql(): string
    {
        $queryData = $this->sql;

        return $queryData['DELETE'] .
            $queryData['FROM'] .
            $queryData['WHERE'];
    }

    public function getSql(): string
    {
        return match ($this->type) {
            QueryTypeEnum::SELECT => $this->getSelectSql(),
            QueryTypeEnum::INSERT => $this->getInsertSql(),
            QueryTypeEnum::UPDATE => $this->getUpdateSql(),
            QueryTypeEnum::DELETE => $this->getDeleteSql(),
        };
    }
}
