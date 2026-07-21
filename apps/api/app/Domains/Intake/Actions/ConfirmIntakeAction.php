<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Enums\IntakeSuggestionKind;
use App\Domains\Intake\Enums\IntakeSuggestionStatus;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Exceptions\IntakeStateConflict;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Models\IntakeSuggestion;
use App\Domains\Intake\Queries\FindOwnedIntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Planner\Actions\CreateTaskAction;
use App\Domains\Resources\Actions\CreateLinkAction;
use App\Domains\SecondBrain\Actions\CreateKnowledgeItemAction;
use App\Domains\SecondBrain\Enums\KnowledgeSourceType;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The only path from suggestions to real records: the user decides every
 * remaining proposal, applied suggestions create tasks, linked resources, or
 * knowledge items through the owning domain actions inside one transaction,
 * and reapplying a
 * confirmation never duplicates created records.
 */
final readonly class ConfirmIntakeAction
{
    public function __construct(
        private FindOwnedIntakeItem $items,
        private CreateTaskAction $tasks,
        private CreateLinkAction $links,
        private CreateKnowledgeItemAction $knowledgeItems,
        private IntakeEventRecorder $events,
    ) {}

    /** @param list<array{id: string, action: 'apply'|'dismiss', overrides: array<string, mixed>}> $decisions */
    public function execute(User $user, string $publicId, array $decisions): IntakeItem
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $decisions): IntakeItem {
                $item = $this->items->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $item);

                if ($item->state === IntakeState::Saved) {
                    return $item;
                }

                if ($item->state !== IntakeState::AwaitingReview) {
                    throw new IntakeStateConflict;
                }

                $suggestions = $item->suggestions()->lockForUpdate()->get()->keyBy('public_id');

                foreach ($decisions as $decision) {
                    $suggestion = $suggestions->get($decision['id']);

                    if (! $suggestion instanceof IntakeSuggestion) {
                        throw ValidationException::withMessages([
                            'decisions' => ['A decision referenced an unknown suggestion.'],
                        ]);
                    }

                    if ($suggestion->status !== IntakeSuggestionStatus::Proposed) {
                        continue;
                    }

                    if ($decision['action'] === 'dismiss') {
                        $suggestion->forceFill(['status' => IntakeSuggestionStatus::Dismissed->value])->save();

                        continue;
                    }

                    $this->apply($user, $suggestion, $decision['overrides']);
                }

                // Undecided proposals are dismissed: nothing is ever created silently.
                foreach ($suggestions as $suggestion) {
                    if ($suggestion->status === IntakeSuggestionStatus::Proposed) {
                        $suggestion->forceFill(['status' => IntakeSuggestionStatus::Dismissed->value])->save();
                    }
                }

                $applied = $item->suggestions()->where('status', IntakeSuggestionStatus::Applied->value)->count();
                $item->forceFill(['state' => IntakeState::Confirmed->value])->save();
                $this->events->record($item, 'confirmed', IntakeState::AwaitingReview, IntakeState::Confirmed, $applied.' suggestions applied');
                $item->forceFill(['state' => IntakeState::Saved->value])->save();
                $this->events->record($item, 'saved', IntakeState::Confirmed, IntakeState::Saved);

                return $item->refresh()->load(['resource', 'artifacts', 'events', 'suggestions']);
            }, 3);
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.confirm');
        }
    }

    /** @param array<string, mixed> $overrides */
    private function apply(User $user, IntakeSuggestion $suggestion, array $overrides): void
    {
        $payload = $suggestion->payload;
        $title = $this->stringOrNull($overrides['title'] ?? null) ?? $this->stringOrNull($payload['title'] ?? null);
        $description = array_key_exists('description', $overrides)
            ? $this->stringOrNull($overrides['description'])
            : $this->stringOrNull($payload['description'] ?? null);
        $coursePublicId = array_key_exists('course_id', $overrides)
            ? $this->stringOrNull($overrides['course_id'])
            : $this->stringOrNull($payload['course_public_id'] ?? null);

        if ($title === null) {
            throw ValidationException::withMessages([
                'decisions' => ['An applied suggestion needs a title.'],
            ]);
        }

        $dueDate = array_key_exists('due_at', $overrides)
            ? $this->stringOrNull($overrides['due_at'])
            : $this->stringOrNull($payload['due_at'] ?? null);
        $url = array_key_exists('url', $overrides)
            ? $this->stringOrNull($overrides['url'])
            : $this->stringOrNull($payload['url'] ?? null);

        // Exhaustive by construction: a new IntakeSuggestionKind case makes this
        // match a PHPStan error rather than silently falling through to the last
        // branch and creating the wrong kind of record.
        match ($suggestion->kind) {
            IntakeSuggestionKind::Task => $this->applyTask($user, $suggestion, $title, $description, $coursePublicId, $dueDate),
            IntakeSuggestionKind::Resource => $this->applyResource($user, $suggestion, $title, $description, $coursePublicId, $url),
            IntakeSuggestionKind::KnowledgeItem => $this->applyKnowledgeItem($user, $suggestion, $title, $description, $url),
        };
    }

    private function applyTask(
        User $user,
        IntakeSuggestion $suggestion,
        string $title,
        ?string $description,
        ?string $coursePublicId,
        ?string $dueDate,
    ): void {
        $task = $this->tasks->execute($user, [
            'title' => $title,
            'description' => $description,
            'course_id' => $coursePublicId,
            'due_at' => $dueDate === null
                ? null
                : CarbonImmutable::createFromFormat('!Y-m-d', $dueDate, 'UTC')->setTime(23, 59),
        ]);

        $suggestion->forceFill([
            'status' => IntakeSuggestionStatus::Applied->value,
            'created_task_id' => $task->getKey(),
        ])->save();
    }

    private function applyResource(
        User $user,
        IntakeSuggestion $suggestion,
        string $title,
        ?string $description,
        ?string $coursePublicId,
        ?string $url,
    ): void {
        if ($url === null) {
            throw ValidationException::withMessages([
                'decisions' => ['An applied resource suggestion needs an https link.'],
            ]);
        }

        $resource = $this->links->execute($user, [
            'title' => $title,
            'description' => $description,
            'course_id' => $coursePublicId,
            'topic_label' => null,
            'url' => $url,
        ]);

        $suggestion->forceFill([
            'status' => IntakeSuggestionStatus::Applied->value,
            'created_resource_id' => $resource->getKey(),
        ])->save();
    }

    /**
     * A confirmed knowledge suggestion becomes a Second Brain record that keeps
     * its provenance in both directions: the knowledge item remembers the intake
     * item it was extracted from, and the suggestion remembers what it created.
     */
    private function applyKnowledgeItem(
        User $user,
        IntakeSuggestion $suggestion,
        string $title,
        ?string $description,
        ?string $url,
    ): void {
        $knowledgeItem = $this->knowledgeItems->execute($user, [
            'title' => $title,
            'summary' => $description,
            'source_type' => $url === null
                ? KnowledgeSourceType::None->value
                : KnowledgeSourceType::Link->value,
            'resource_id' => null,
            'source_url' => $url,
            'authors' => null,
            'published_year' => null,
            'venue' => null,
            'doi' => null,
            // The intake router's purpose is advisory; the owner sets it from
            // the Second Brain rather than having it stamped on confirmation.
            'purpose' => null,
        ]);

        $knowledgeItem->forceFill(['intake_item_id' => $suggestion->intake_item_id])->save();

        $suggestion->forceFill([
            'status' => IntakeSuggestionStatus::Applied->value,
            'created_knowledge_item_id' => $knowledgeItem->getKey(),
        ])->save();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
