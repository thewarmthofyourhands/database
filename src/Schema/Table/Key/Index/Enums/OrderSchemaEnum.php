<?php

declare(strict_types=1);

namespace Eva\Database\Schema\Table\Key\Index\Enums;

enum OrderSchemaEnum: string
{
    case DESC = 'DESC';
    case ASC = 'ASC';
}
