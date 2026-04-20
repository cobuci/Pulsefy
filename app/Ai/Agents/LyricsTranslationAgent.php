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
final class LyricsTranslationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return File::get(resource_path('prompts/lyrics/translation.system.md'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $schema): array => [
                        'index' => $schema->integer()->required(),
                        'timestamp' => $schema->string(),
                        'source_lang' => $schema->string()->enum(['en', 'pt-BR', 'other', 'mixed'])->required(),
                        'pt_br' => $schema->string(),
                        'en' => $schema->string(),
                    ])
                )
                ->required(),
        ];
    }
}
