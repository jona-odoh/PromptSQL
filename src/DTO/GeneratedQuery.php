<?php

declare(strict_types=1);

namespace PromptSQL\DTO;

final class GeneratedQuery
{
    public function __construct(
        public readonly string $sql,
        /** @var array<string, mixed> */
        public readonly array $parameters,
        /** @var array<int, string> */
        public readonly array $tables,
        /** @var array<int, string> */
        public readonly array $columns,
        public readonly string $reasoning = ''
    ) {
    }
}