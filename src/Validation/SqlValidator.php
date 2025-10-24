<?php

declare(strict_types=1);

namespace PromptSQL\Validation;

use PromptSQL\Contracts\QueryValidatorInterface;
use PromptSQL\Exceptions\ValidationException;

final class SqlValidator implements QueryValidatorInterface
{
    /**
     * Strict validation to ensure:
     * - Single statement
     * - Read-only (SELECT or WITH ... SELECT)
     * - No dangerous keywords
     * - Tables are allow-listed
     * - Optional: star usage controlled
     */
    public function validate(string $sql, array $allowList, bool $allowSelectStar = false): void
    {
        $clean = $this->stripCommentsAndWhitespace($sql);

        if ($this->hasSemicolon($clean)) {
            throw new ValidationException('Multiple statements are not allowed.');
        }

        if (!$this->isSelectOnly($clean)) {
            throw new ValidationException('Only SELECT or WITH ... SELECT statements are allowed.');
        }

        $forbidden = [
            'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'REPLACE', 'UPSERT',
            'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE',
            'CALL', 'DO', 'PREPARE', 'EXECUTE', 'COPY', 'VACUUM', 'ANALYZE'
        ];
        foreach ($forbidden as $kw) {
            if ($this->containsKeyword($clean, $kw)) {
                throw new ValidationException("Forbidden keyword detected: {$kw}");
            }
        }

        if (!$allowSelectStar && $this->containsSelectStar($clean)) {
            throw new ValidationException('SELECT * is not allowed by configuration.');
        }

        $tablesInQuery = $this->extractBaseTables($clean);
        $allowedTables = array_map('strtolower', array_keys($allowList));
        foreach ($tablesInQuery as $t) {
            if (!in_array(strtolower($t), $allowedTables, true)) {
                throw new ValidationException("Table '{$t}' is not in the allow-list.");
            }
        }

        // Optional: basic check that all literals are parameterized.
        // Disallow obvious unparameterized string literals: 'text' or "text" not part of identifiers.
        if ($this->hasUnparameterizedStringLiteral($clean)) {
            throw new ValidationException('Unparameterized string literal detected.');
        }
    }

    private function stripCommentsAndWhitespace(string $sql): string
    {
        // Remove /* ... */ and -- ... comments, and trim.
        $withoutBlock = preg_replace('#/\*.*?\*/#s', ' ', $sql) ?? $sql;
        $withoutLine = preg_replace('#--.*$#m', ' ', $withoutBlock) ?? $withoutBlock;
        return trim(preg_replace('/\s+/', ' ', $withoutLine) ?? $withoutLine);
    }

    private function hasSemicolon(string $sql): bool
    {
        // Reject trailing semicolon to avoid statement chaining
        return str_contains($sql, ';');
    }

    private function isSelectOnly(string $sql): bool
    {
        $upper = strtoupper($sql);
        return str_starts_with($upper, 'SELECT ') || str_starts_with($upper, 'WITH ');
    }

    private function containsKeyword(string $sql, string $keyword): bool
    {
        return (bool) preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $sql);
    }

    private function containsSelectStar(string $sql): bool
    {
        // Detect SELECT * (possibly with qualifier)
        // e.g., SELECT *, SELECT t.*,
        return (bool) preg_match('/\bSELECT\b\s+([a-zA-Z0-9_]+\.)?\*\b/i', $sql);
    }

    /**
     * Attempt to extract physical base table names after FROM or JOIN.
     * This is a heuristic and not a full SQL parser.
     *
     * @return array<int, string>
     */
    private function extractBaseTables(string $sql): array
    {
        $tables = [];
        if (preg_match_all('/\b(FROM|JOIN)\s+([`"\[])?([a-zA-Z0-9_.]+)\2?/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $full = $m[3];
                // Handle schema.table by taking last segment
                $parts = explode('.', $full);
                $tables[] = trim(end($parts) ?: $full, '"`[]');
            }
        }
        return array_values(array_unique($tables));
    }

    private function hasUnparameterizedStringLiteral(string $sql): bool
    {
        // Roughly detect quoted string not within identifier quotes for PostgreSQL/MySQL
        // Disallow 'value' unless it is within VALUES keyword (which is forbidden anyway).
        // Also disallow explicit date/time string literals.
        // Allow single quotes in PostgreSQL escape strings is still a literal.
        if (preg_match("/'(?:[^']|'')*'/", $sql)) {
            // Could be something like '2023-01-01' in WHERE; should be parameterized.
            return true;
        }
        // Double-quoted strings are identifiers in PostgreSQL, but strings in MySQL mode ANSI; often better to forbid.
        // Allow double quotes only when surrounding identifiers in SELECT list or table names (hard to detect).
        // Being conservative: if we see "text" pattern in WHERE context would be ideal; keep simple:
        // We won't reject generic double quotes blindly to not block identifiers.
        return false;
    }
}