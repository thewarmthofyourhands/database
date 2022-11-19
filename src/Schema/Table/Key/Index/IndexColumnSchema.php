<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key\Index;

use Eva\Database\Schema\Table\Key\Index\Enums\OrderSchemaEnum;

class IndexColumnSchema
{
    public function __construct(
        private readonly string $name,
        private readonly OrderSchemaEnum $order,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrder(): OrderSchemaEnum
    {
        return $this->order;
    }
}
