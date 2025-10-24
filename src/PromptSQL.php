<?php

declare(strict_types=1);

namespace PromptSQL;

use PromptSQL\Config\PromptSQLConfig;
use PromptSQL\Contracts\DatabaseAdapterInterface;
use PromptSQL\Contracts\NaturalLanguageToSQLInterface;
use PromptSQL\Contracts\QueryValidatorInterface;
use PromptSQL\DTO\QueryResult;
use PromptSQL\Enums\QueryStatus;
use PromptSQL\Exceptions\ExecutionException;
use PromptSQL\Exceptions\GenerationException;
use PromptSQL\Exceptions\ValidationException;

final class PromptSQL
{
    public function __construct(
        private readonly PromptSQLConfig $config,
        private readonly NaturalLanguageToSQLInterface $generator,
        private readonly QueryValidatorInterface $validator,
        private readonly DatabaseAdapterInterface $db
    ) {
    }

    public function ask(string $question): QueryResult
    {
        try {
            $generated = $this->generator->generate(
                $question,
                $this->db->getDriver(),
                $this->config->allowList,
                $this->config->allowSelectStar,
                $this->config->tableDescriptions,
                $this->config->columnDescriptions
            );

            // Validate SQL against hard rules and allow-list
            $this->validator->validate($generated->sql, $this->config->allowList, $this->config->allowSelectStar);
        } catch (GenerationException $e) {
            return new QueryResult(QueryStatus::Failed, '', [], 'Generation failed: ' . $e->getMessage());
        } catch (ValidationException $e) {
            return new QueryResult(QueryStatus::Rejected, '', [], 'Query rejected: ' . $e->getMessage());
        }

        try {
            $rows = $this->db->execute($generated->sql, $generated->parameters);
            return new QueryResult(QueryStatus::Executed, $generated->sql, $rows);
        } catch (ExecutionException $e) {
            return new QueryResult(QueryStatus::Failed, $generated->sql, [], 'Execution failed: ' . $e->getMessage());
        }
    }
}