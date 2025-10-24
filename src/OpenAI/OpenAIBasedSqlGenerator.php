<?php

declare(strict_types=1);

namespace PromptSQL\OpenAI;

use PromptSQL\Contracts\NaturalLanguageToSQLInterface;
use PromptSQL\Contracts\OpenAIClientInterface;
use PromptSQL\DTO\GeneratedQuery;
use PromptSQL\Enums\DatabaseDriver;
use PromptSQL\Exceptions\GenerationException;

final class OpenAIBasedSqlGenerator implements NaturalLanguageToSQLInterface
{
    public function __construct(
        private readonly OpenAIClientInterface $client,
        private readonly string $model = 'gpt-4o-mini'
    ) {
    }

    public function generate(
        string $question,
        DatabaseDriver $driver,
        array $allowList,
        bool $allowSelectStar = false,
        array $tableDescriptions = [],
        array $columnDescriptions = []
    ): GeneratedQuery {
        $system = $this->buildSystemPrompt($driver, $allowList, $allowSelectStar, $tableDescriptions, $columnDescriptions);
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ];

        $content = $this->client->chat($messages, $this->model, 0.0);

        try {
            /** @var array{
             *  sql: string,
             *  parameters?: array<string, mixed>,
             *  tables?: array<int, string>,
             *  columns?: array<int, string>,
             *  reasoning?: string
             * } $decoded
             */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new GenerationException('Model output is not valid JSON: ' . $e->getMessage());
        }

        $sql = trim((string) ($decoded['sql'] ?? ''));
        if ($sql === '') {
            throw new GenerationException('Model did not return an SQL string.');
        }

        $parameters = (array) ($decoded['parameters'] ?? []);
        $tables = array_values(array_filter(array_map('strval', $decoded['tables'] ?? [])));
        $columns = array_values(array_filter(array_map('strval', $decoded['columns'] ?? [])));
        $reasoning = (string) ($decoded['reasoning'] ?? '');

        return new GeneratedQuery($sql, $parameters, $tables, $columns, $reasoning);
    }

    private function buildSystemPrompt(
        DatabaseDriver $driver,
        array $allowList,
        bool $allowSelectStar,
        array $tableDescriptions,
        array $columnDescriptions
    ): string {
        $allowInfo = $this->allowListToText($allowList, $tableDescriptions, $columnDescriptions);

        return trim(<<<SYS
You are PromptSQL, an expert SQL assistant. Your job is to convert a user's natural language question into ONE safe, read-only SQL statement for the {$driver->value} dialect.

HARD RULES:
- OUTPUT MUST BE STRICT JSON with keys: sql, parameters, tables, columns, reasoning.
- sql must be a single SELECT-only statement. CTEs (WITH ...) are allowed if they end in a SELECT.
- DO NOT include any mutating or dangerous keywords (INSERT, UPDATE, DELETE, MERGE, REPLACE, UPSERT, DROP, ALTER, CREATE, TRUNCATE, GRANT, REVOKE, CALL, DO, PREPARE, EXECUTE, COPY, VACUUM, ANALYZE).
- ALWAYS use parameter placeholders with named parameters for all literal values. No string or date literals inline.
- For arrays, use a single named parameter and assume the client will expand it (e.g., WHERE id IN (:ids)).
- Return "tables" as the list of base tables referenced (FROM/JOIN).
- Return "columns" as the list of columns projected in the SELECT clause (best effort).
- Only use tables and columns from the allow-list. If insufficient, pick the best matching allowed tables/columns.
- SELECT * is %s.

ALLOWED SCHEMA (tables and columns):
%s

Formatting template for your JSON response:
{
  "sql": "SELECT ... WHERE col = :param",
  "parameters": {"param": "value"}, 
  "tables": ["table1", "table2"],
  "columns": ["t.id", "t.name"],
  "reasoning": "brief why these tables/columns answer the question"
}
SYS, 'SYS');
    }

    private function allowListToText(
        array $allowList,
        array $tableDescriptions,
        array $columnDescriptions
    ): string {
        $lines = [];
        foreach ($allowList as $table => $cols) {
            $desc = $tableDescriptions[$table] ?? '';
            $colDescs = $columnDescriptions[$table] ?? [];
            $colLines = [];
            if (!empty($cols)) {
                foreach ($cols as $c) {
                    $cdesc = $colDescs[$c] ?? '';
                    $colLines[] = "- {$c}" . ($cdesc !== '' ? " ({$cdesc})" : '');
                }
            } else {
                $colLines[] = "- (all columns allowed)";
            }
            $lines[] = sprintf(
                "* %s%s\n%s",
                $table,
                $desc !== '' ? " ({$desc})" : '',
                implode("\n", $colLines)
            );
        }
        return implode("\n", $lines);
    }
}