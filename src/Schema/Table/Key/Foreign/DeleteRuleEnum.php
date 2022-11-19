<?php

declare(strict_types=1);

namespace Eva\Database\Schema;

enum DeleteRuleEnum: string
{
    case RESTRICT = 'RESTRICT';
    case NULL = 'NULL';
    case CASCADE = 'CASCADE';
}
