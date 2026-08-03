<?php

namespace App\Services;

use Anthropic\Client;

class ClaudeService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.key'));
    }

    public function generateWod(string $wodType, string $className): array
    {
        $message = $this->client->messages->create(
            model: 'claude-opus-4-8',
            maxTokens: 1024,
            messages: [
                [
                    'role'    => 'user',
                    'content' => "You are a CrossFit programming expert. Generate a {$wodType} workout for a class called \"{$className}\". Return ONLY a JSON array of exercise name strings, for example: [\"Back Squat\", \"Box Jumps\", \"Burpees\"]. Include 4-8 exercises appropriate for the {$wodType} format. No explanation, no markdown, just the raw JSON array.",
                ],
            ],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        if (preg_match('/\[.*?\]/s', $text, $matches)) {
            $exercises = json_decode($matches[0], true);
            if (is_array($exercises)) {
                return array_values(array_filter($exercises, fn ($e) => is_string($e)));
            }
        }

        return [];
    }
}
