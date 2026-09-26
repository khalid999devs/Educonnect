<?php

declare(strict_types=1);

namespace Tests\Feature\Templates\Concerns;

use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

trait InteractsWithTemplates
{
    /**
     * Versions can only be attached while a template is a draft, so published
     * fixtures walk the real draft -> in_review -> published transitions.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $versionAttributes
     */
    private function publishedTemplate(
        array $attributes = [],
        array $versionAttributes = [],
        ?CarbonImmutable $reviewedAt = null,
    ): Template {
        $template = Template::factory()->create($attributes);
        TemplateVersion::factory()->create([
            'template_id' => $template->getKey(),
            ...$versionAttributes,
        ]);
        $reviewedAt ??= CarbonImmutable::now();

        DB::table('templates')->where('id', $template->getKey())->update(['state' => 'in_review']);
        DB::table('templates')->where('id', $template->getKey())->update([
            'state' => 'published',
            'last_reviewed_at' => $reviewedAt,
            'published_at' => $reviewedAt,
        ]);

        return $template->refresh();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'template-test-token',
        ];
    }

    private function configureBrowserBoundary(): void
    {
        config()->set([
            'app.frontend_url' => 'http://localhost:3000',
            'app.admin_url' => 'http://localhost:3001',
            'cors.allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],
            'sanctum.stateful' => ['localhost:3000', 'localhost:3001'],
        ]);
    }
}
