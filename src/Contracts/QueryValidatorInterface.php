<?php

declare(strict_types=1);

namespace PromptSQL\Contracts;

interface QueryValidatorInterface
{
    /**
     * Validate the SQL for read-only, allow-listed safety. Throws on failure.
     *
     * @param string $sql
     * @param array<string, array<int, string>> $allowList table => allowed columns (empty means no column restriction)
     * @param bool $allowSelectStar
     */
    public function validate(string $sql, array $allowList, bool $allowSelectStar = false): void;
}