<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Enums;

enum KeySchemaTypeEnum: string
{
    case PRIMARY = 'PRIMARY';
    case INDEX = 'INDEX';
    case UNIQUE = 'UNIQUE';
    case FOREIGN = 'FOREIGN';
}
