<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Enums\TemplateCopyDestination;
use App\Domains\Templates\Enums\TemplateFormat;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserTemplateCopy> */
final class UserTemplateCopyFactory extends Factory
{
    protected $model = UserTemplateCopy::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'template_version_id' => fn (array $attributes) => TemplateVersion::factory()->create([
                'template_id' => $attributes['template_id'],
            ])->getKey(),
            'destination' => TemplateCopyDestination::Dashboard->value,
            'course_id' => null,
            'title' => 'My Study Plan Copy',
            'format' => TemplateFormat::Markdown->value,
            'body' => "## Goal\n\nMy own adapted study plan.",
            'version' => 1,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
