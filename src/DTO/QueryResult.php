<?php

declare(strict_types=1);

namespace PromptSQL\DTO;

use PromptSQL\Enums\QueryStatus;

final class QueryResult
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public readonly QueryStatus $status,
        public readonly string $sql,
        public readonly array $rows = [],
        public readonly string $message = ''
    ) {
    }
}