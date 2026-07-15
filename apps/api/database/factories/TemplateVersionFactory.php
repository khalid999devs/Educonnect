<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Enums\TemplateFormat;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TemplateVersion> */
final class TemplateVersionFactory extends Factory
{
    protected $model = TemplateVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'version_number' => 1,
            'format' => TemplateFormat::Markdown->value,
            'body' => "## Goal\n\nDescribe the bounded academic goal here.\n\n## Steps\n\n1. Plan the work.\n2. Do the work.\n3. Review the result yourself.",
            'change_note' => null,
        ];
    }
}
