<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key;

use Eva\Database\Schema\DeleteRuleEnum;
use Eva\Database\Schema\KeySchemaTypeEnum;
use Eva\Database\Schema\Table\KeySchema;
use Eva\Database\Schema\UpdateRuleEnum;

class ForeignKeySchema extends KeySchema
{
    public function __construct(
        string $name,
        private readonly string $column,
        private readonly string $referenceTableName,
        private readonly string $referenceColumnName,
        private readonly DeleteRuleEnum $deleteRule = DeleteRuleEnum::RESTRICT,
        private readonly UpdateRuleEnum $updateRule = UpdateRuleEnum::RESTRICT,
    ) {
        parent::__construct($name, KeySchemaTypeEnum::FOREIGN);
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getReferenceTableName(): string
    {
        return $this->referenceTableName;
    }

    public function getReferenceColumnName(): string
    {
        return $this->referenceColumnName;
    }

    public function getDeleteRule(): DeleteRuleEnum
    {
        return $this->deleteRule;
    }

    public function getUpdateRule(): UpdateRuleEnum
    {
        return $this->updateRule;
    }
}
