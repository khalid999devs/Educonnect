<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Guidance\Models\UserWorkflowPreference;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Guidance\Support\PublishedWorkflowVisibility;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildCategoryGuidance
{
    public const MAX_ITEMS_PER_COLLECTION = 10;

    /**
     * @return array{
     *     category: ToolCategory,
     *     tools: Collection<int, Tool>,
     *     prompts: Collection<int, PromptTemplate>,
     *     workflows: Collection<int, WorkflowRecipe>,
     * }
     */
    public function execute(User $user, string $categorySlug): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $category = ToolCategory::query()->where('slug', $categorySlug)->first();

            if (! $category instanceof ToolCategory) {
                throw new NotFoundHttpException;
            }

            $toolQuery = Tool::query()
                ->select('tools.*')
                ->addSelect([
                    'viewer_preference_state' => UserToolPreference::query()
                        ->select('state')
                        ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                        ->where('user_tool_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->where('tools.tool_category_id', $category->getKey())
                ->with('category');
            PublishedToolVisibility::apply($toolQuery);
            $tools = $toolQuery
                ->orderByRaw('LOWER(tools.name)')
                ->orderBy('public_id')
                ->limit(self::MAX_ITEMS_PER_COLLECTION)
                ->get();

            $promptQuery = PromptTemplate::query()
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
                ->where('prompt_templates.tool_category_id', $category->getKey())
                ->with([
                    'category',
                    'relatedTools' => static function (Relation $relatedTools): void {
                        if (! $relatedTools instanceof BelongsToMany) {
                            return;
                        }

                        /** @var Builder<Tool> $builder */
                        $builder = $relatedTools->getQuery();
                        PublishedToolVisibility::apply($builder);
                        $relatedTools->orderBy('tools.name');
                    },
                ]);
            PublishedPromptVisibility::apply($promptQuery);
            $prompts = $promptQuery
                ->orderByRaw('LOWER(prompt_templates.title)')
                ->orderBy('public_id')
                ->limit(self::MAX_ITEMS_PER_COLLECTION)
                ->get();

            $workflowQuery = WorkflowRecipe::query()
                ->select('workflow_recipes.*')
                ->addSelect([
                    'viewer_preference_state' => UserWorkflowPreference::query()
                        ->select('state')
                        ->whereColumn('user_workflow_preferences.workflow_recipe_id', 'workflow_recipes.id')
                        ->where('user_workflow_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->where('workflow_recipes.tool_category_id', $category->getKey())
                ->with(['category', 'steps', 'steps.tool', 'steps.promptTemplate']);
            PublishedWorkflowVisibility::apply($workflowQuery);
            $workflows = $workflowQuery
                ->orderByRaw('LOWER(workflow_recipes.title)')
                ->orderBy('public_id')
                ->limit(self::MAX_ITEMS_PER_COLLECTION)
                ->get();

            return [
                'category' => $category,
                'tools' => $tools,
                'prompts' => $prompts,
                'workflows' => $workflows,
            ];
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'guidance.read');
        }
    }
}
