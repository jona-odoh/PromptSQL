<?php

declare(strict_types=1);

namespace PromptSQL\Contracts;

interface OpenAIClientInterface
{
    /**
     * Perform a chat completion request to OpenAI and return the raw assistant message content.
     *
     * @param array<array{role:string, content:string}> $messages
     * @param string $model
     * @param float $temperature
     *
     * @return string The assistant message content.
     */
    public function chat(array $messages, string $model, float $temperature = 0.0): string;
}