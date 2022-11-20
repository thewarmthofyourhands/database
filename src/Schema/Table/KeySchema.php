<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table;


use Eva\Database\Schema\Table\Enums\KeySchemaTypeEnum;

abstract class KeySchema
{
    public function __construct(
        private readonly string $name,
        private readonly KeySchemaTypeEnum $type,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): KeySchemaTypeEnum
    {
        return $this->type;
    }
}
