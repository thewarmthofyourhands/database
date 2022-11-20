<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key\Foreign;

enum DeleteRuleEnum: string
{
    case RESTRICT = 'RESTRICT';
    case NULL = 'NULL';
    case CASCADE = 'CASCADE';
}
