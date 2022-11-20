<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key;

use Eva\Database\Schema\Table\Key\Index\Enums\IndexEngineEnum;
use Eva\Database\Schema\Table\Enums\KeySchemaTypeEnum;
use Eva\Database\Schema\Table\Key\Index\IndexColumnSchema;
use Eva\Database\Schema\Table\KeySchema;

class IndexKeySchema extends KeySchema
{
    protected const TYPE = KeySchemaTypeEnum::INDEX;

    public function __construct(
        string $name,
        /** IndexColumnSchema[] $columnSchemaList */
        private readonly array $columnSchemaList,
        private readonly IndexEngineEnum $engine = IndexEngineEnum::BTREE,
    ) {
        parent::__construct($name, static::TYPE);
    }

    /**
     * @return IndexColumnSchema[]
     */
    public function getColumnSchemaList(): array
    {
        return $this->columnSchemaList;
    }

    public function getEngine(): IndexEngineEnum
    {
        return $this->engine;
    }
}
