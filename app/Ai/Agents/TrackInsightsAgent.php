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
final class TrackInsightsAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return File::get(resource_path('prompts/track/insights.system.md'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary_en' => $schema->string()->required(),
            'summary_pt' => $schema->string()->required(),
            'mood_en' => $schema->string()->required(),
            'mood_pt' => $schema->string()->required(),
            'meaning_en' => $schema->string()->required(),
            'meaning_pt' => $schema->string()->required(),
            'themes_en' => $schema->array()->items($schema->string())->required(),
            'themes_pt' => $schema->array()->items($schema->string())->required(),
            'trivia_en' => $schema->array()->items($schema->string())->required(),
            'trivia_pt' => $schema->array()->items($schema->string())->required(),
            'similar_en' => $schema->array()->items($schema->string())->required(),
            'similar_pt' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
