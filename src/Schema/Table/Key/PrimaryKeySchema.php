<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key;

use Eva\Database\Schema\KeySchemaTypeEnum;
use Eva\Database\Schema\Table\KeySchema;

class PrimaryKeySchema extends KeySchema
{
    public function __construct(string $name, private readonly string $column)
    {
        parent::__construct($name, KeySchemaTypeEnum::PRIMARY);
    }

    public function getColumn(): string
    {
        return $this->column;
    }
}
