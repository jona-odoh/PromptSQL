<?php

declare(strict_types=1);

namespace PromptSQL\Gemini;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PromptSQL\Contracts\OpenAIClientInterface;
use PromptSQL\Exceptions\GenerationException;
use Psr\Log\LoggerInterface;

final class GuzzleGeminiClient implements OpenAIClientInterface
{
    private readonly Client $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly ?LoggerInterface $logger = null,
        ?Client $httpClient = null
    ) {
        $this->http = $httpClient ?? new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/openai/',
            'timeout' => 30,
        ]);
    }

    public function chat(array $messages, string $model, float $temperature = 0.0): string
    {
        try {
            $response = $this->http->post('chat/completions', [
                'json' => [
                    'model' => $model,
                    'temperature' => $temperature,
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object'],
                ],
                'headers' => [
                    'Authorization' => "Bearer {
                       $this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'key' => $this->apiKey,
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $content = $payload['choices'][0]['message']['content'] ?? '';
            if (!is_string($content) || $content === '') {
                throw new GenerationException('Empty response content from Gemini.');
            }

            return $content;
        } catch (GuzzleException $e) {
            $this->logger?->error('Gemini request failed', ['exception' => $e]);
            throw new GenerationException('Gemini request failed: ' . $e->getMessage());
        } catch (\JsonException $e) {
            $this->logger?->error('Failed to parse Gemini response JSON', ['exception' => $e]);
            throw new GenerationException('Failed to parse Gemini response JSON: ' . $e->getMessage());
        }
    }
}