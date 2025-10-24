<?php

declare(strict_types=1);

namespace PromptSQL\Contracts;

use PromptSQL\DTO\GeneratedQuery;
use PromptSQL\Enums\DatabaseDriver;

interface NaturalLanguageToSQLInterface
{
    /**
     * Convert a natural language question into a safe, parameterized SELECT SQL query with metadata.
     *
     * @param string $question
     * @param DatabaseDriver $driver
     * @param array<string, array<int, string>> $allowList Map of table => list of allowed columns (empty list means all columns allowed)
     * @param bool $allowSelectStar Whether SELECT * is permitted
     * @param array<string, string> $tableDescriptions Optional per-table descriptions for better generation
     * @param array<string, array<string, string>> $columnDescriptions Optional per-table column descriptions
     */
    public function generate(
        string $question,
        DatabaseDriver $driver,
        array $allowList,
        bool $allowSelectStar = false,
        array $tableDescriptions = [],
        array $columnDescriptions = []
    ): GeneratedQuery;
}