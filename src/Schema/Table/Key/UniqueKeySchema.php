<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key;

use Eva\Database\Schema\Table\Enums\KeySchemaTypeEnum;

class UniqueKeySchema extends IndexKeySchema
{
    protected const TYPE = KeySchemaTypeEnum::UNIQUE;
}
