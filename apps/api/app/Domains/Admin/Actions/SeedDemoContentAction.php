<?php

declare(strict_types=1);

namespace App\Domains\Admin\Actions;

use App\Domains\Audit\Enums\AuditAction;
use App\Domains\Audit\Support\AuditRecorder;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Tools\Models\Tool;
use App\Domains\Users\Models\User;
use Database\Seeders\GuidanceCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Populates the deterministic guidance launch catalog on demand. Deliberately
 * additive and idempotent — it seeds the curated catalog only when it is absent
 * and never deletes existing data, so it is safe to run against a shared
 * environment (a destructive wipe-and-reset belongs to an isolated demo tenant
 * in staging, not this control). The action is recorded in the audit log.
 */
final readonly class SeedDemoContentAction
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * @return array<string, int>
     */
    public function execute(User $actor, string $reason, string $requestId): array
    {
        if (! $actor->hasCapability(CapabilityKey::ContentCurate)) {
            throw new AuthorizationException('Seeding demo content is not allowed.');
        }

        return DB::transaction(function () use ($actor, $reason, $requestId): array {
            (new GuidanceCatalogSeeder)->run();

            $this->auditRecorder->record(
                actor: $actor,
                action: AuditAction::DemoDataSeeded,
                subjectType: 'demo_data',
                subjectId: 'guidance-catalog',
                reason: $reason,
                requestId: $requestId,
                beforeState: ['demo_data' => ['requested']],
                afterState: ['demo_data' => ['guidance-catalog']],
            );

            return [
                'tools' => Tool::query()->count(),
                'prompts' => PromptTemplate::query()->count(),
                'workflows' => WorkflowRecipe::query()->count(),
            ];
        }, 3);
    }
}
