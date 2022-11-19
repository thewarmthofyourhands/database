<?php

declare(strict_types=1);

namespace Eva\Database;

enum QueryTypeEnum: string
{
    case SELECT = 'SELECT';
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
}
