<?php

declare(strict_types=1);

namespace Tests\Feature\Study\Concerns;

use App\Domains\Intake\Models\IntakeArtifact;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Study\Contracts\StudyGenerator;
use App\Domains\Users\Models\User;
use Tests\Support\FakeStudyGenerator;

trait InteractsWithStudy
{
    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'X-XSRF-TOKEN' => 'study-test-token',
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

    /**
     * A knowledge item with a real join path to extracted text: an intake item,
     * its extracted-text artifact, and the `intake_item_id` link that
     * ..._000019 added. Without the link there is nothing to study from.
     */
    private function studyableItem(User $owner, string $text = 'Chapter four covers photosynthesis in C4 plants.'): KnowledgeItem
    {
        $intake = IntakeItem::factory()->for($owner, 'user')->extracted()->create();
        IntakeArtifact::factory()->create([
            'intake_item_id' => $intake->getKey(),
            'text_content' => $text,
        ]);

        return KnowledgeItem::factory()->for($owner, 'user')->fromIntake($intake)->create();
    }

    /** A knowledge item with no intake link, and therefore nothing to study. */
    private function unstudyableItem(User $owner): KnowledgeItem
    {
        return KnowledgeItem::factory()->for($owner, 'user')->create();
    }

    private function bindGenerator(FakeStudyGenerator $generator): FakeStudyGenerator
    {
        $this->app->instance(StudyGenerator::class, $generator);

        return $generator;
    }

    private function url(string $itemPublicId): string
    {
        return "/api/v1/study/{$itemPublicId}/generations";
    }
}
