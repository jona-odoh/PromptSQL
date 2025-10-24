<?php

declare(strict_types=1);

namespace PromptSQL\Enums;

enum QueryStatus: string
{
    case Generated = 'generated';
    case Rejected = 'rejected';
    case Validated = 'validated';
    case Executed = 'executed';
    case Failed = 'failed';
}