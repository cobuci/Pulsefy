<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
final class DiscoveryRecommendationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return File::get(resource_path('prompts/discovery/recommendations.system.md'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tracks' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'track' => $schema->string()->required(),
                    'artist' => $schema->string()->required(),
                    'reason' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
