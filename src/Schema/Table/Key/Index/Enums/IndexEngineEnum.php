<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key\Index\Enums;

enum IndexEngineEnum: string
{
    case BTREE = 'BTREE';
    case HASH = 'HASH';
}
