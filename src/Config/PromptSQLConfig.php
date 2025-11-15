<?php

declare(strict_types=1);

namespace PromptSQL\Config;

use PromptSQL\Enums\DatabaseDriver;

final class PromptSQLConfig
{
    /**
     * @param DatabaseDriver $driver
     * @param string $geminiApiKey
     * @param string $geminiModel
     * @param array<string, mixed> $pdoOptions
     * @param array<string, array<int, string>> $allowList table => columns (empty list means all columns allowed)
     * @param bool $allowSelectStar Whether SELECT * is allowed
     * @param array<string, string> $tableDescriptions Optional hints per table
     * @param array<string, array<string, string>> $columnDescriptions Optional hints per table column
     */
    public function __construct(
        public readonly DatabaseDriver $driver,
        public readonly string $dsn,
        public readonly string $username,
        public readonly string $password,
        public readonly string $geminiApiKey,
        public readonly string $geminiModel = 'gemini-1.5-flash',
        public readonly array $pdoOptions = [],
        public readonly array $allowList = [],
        public readonly bool $allowSelectStar = false,
        public readonly array $tableDescriptions = [],
        public readonly array $columnDescriptions = []
    ) {
    }
}