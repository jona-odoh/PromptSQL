<?php

declare(strict_types=1);

namespace PromptSQL\Contracts;

use PromptSQL\Enums\DatabaseDriver;

interface DatabaseAdapterInterface
{
    public function getDriver(): DatabaseDriver;

    /**
     * Execute a validated SELECT SQL query with parameters.
     *
     * @param string $sql Parameterized SQL
     * @param array<string, mixed> $params Named parameters
     *
     * @return array<int, array<string, mixed>> Result rows
     */
    public function execute(string $sql, array $params = []): array;

    /**
     * Optional: fetch minimal schema info to guide the LLM.
     *
     * @return array{
     *   tables: array<int, string>,
     *   columns: array<string, array<int, string>>
     * }
     */
    public function getSchemaOverview(): array;
}