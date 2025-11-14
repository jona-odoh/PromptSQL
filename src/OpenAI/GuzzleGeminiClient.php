<?php

declare(strict_types=1);

namespace PromptSQL\OpenAI;

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
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'timeout' => 30,
        ]);
    }

    public function chat(array $messages, string $model, float $temperature = 0.0): string
    {
        try {
            // Convert OpenAI-style messages to Gemini format
            $contents = $this->convertMessagesToGeminiFormat($messages);

            $response = $this->http->post("models/{$model}:generateContent", [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'responseMimeType' => 'application/json',
                    ],
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            
            // Extract content from Gemini response format
            $content = $payload['candidates'][0]['content']['parts'][0]['text'] ?? '';
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

    /**
     * Convert OpenAI-style messages to Gemini format
     * 
     * @param array<array{role:string, content:string}> $messages
     * @return array<array{role:string, parts:array<array{text:string}>}>
     */
    private function convertMessagesToGeminiFormat(array $messages): array
    {
        $contents = [];
        
        foreach ($messages as $message) {
            $role = $message['role'] === 'system' || $message['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $message['content']],
                ],
            ];
        }

        return $contents;
    }
}
