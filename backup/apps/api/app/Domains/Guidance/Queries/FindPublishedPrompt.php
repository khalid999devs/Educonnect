<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindPublishedPrompt
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): PromptTemplate
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = PromptTemplate::query()
                ->select('prompt_templates.*')
                ->addSelect([
                    'viewer_preference_state' => UserPromptPreference::query()
                        ->select('state')
                        ->whereColumn('user_prompt_preferences.prompt_template_id', 'prompt_templates.id')
                        ->where('user_prompt_preferences.user_id', $user->getKey())
                        ->limit(1),
                    'viewer_copy_count' => UserPromptCopy::query()
                        ->select('copy_count')
                        ->whereColumn('user_prompt_copies.prompt_template_id', 'prompt_templates.id')
                        ->where('user_prompt_copies.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->where('prompt_templates.public_id', $publicId)
                ->with([
                    'category',
                    'relatedTools' => static function (Relation $tools): void {
                        if (! $tools instanceof BelongsToMany) {
                            return;
                        }

                        /** @var Builder<Tool> $builder */
                        $builder = $tools->getQuery();
                        PublishedToolVisibility::apply($builder);
                        $tools->orderBy('tools.name');
                    },
                ]);

            PublishedPromptVisibility::apply($query);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $prompt = $query->first();

            if (! $prompt instanceof PromptTemplate) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $prompt);

            return $prompt;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw GuidancePersistenceFailure::fromQueryException($exception, 'prompt.read');
        }
    }
}
