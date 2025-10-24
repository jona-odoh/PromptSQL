<?php

declare(strict_types=1);

namespace PromptSQL\Enums;

enum DatabaseDriver: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';
}