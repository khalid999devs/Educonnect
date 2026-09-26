<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Domains\Guidance\Support\PublishedPromptVisibility;
use App\Domains\Guidance\Support\PublishedWorkflowVisibility;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Support\PublishedTemplateVisibility;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;
use App\Support\CacheVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildCategoryGuidance
{
    public const MAX_ITEMS_PER_COLLECTION = 10;

    public function __construct(private readonly CacheVersion $cacheVersion) {}

    /**
     * @return array{
     *     category: ToolCategory,
     *     tools: Collection<int, Tool>,
     *     prompts: Collection<int, PromptTemplate>,
     *     workflows: Collection<int, WorkflowRecipe>,
     *     templates: Collection<int, Template>,
     * }
     */
    public function execute(User $user, string $categorySlug): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $content = $this->content($categorySlug);
            $this->applyViewerState($user, $content);

            return $content;
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'guidance.read');
        }
    }

    /**
     * The published content is identical for every student and changes only via
     * curation, so it is cached under a content version that curation bumps.
     *
     * @return array{
     *     category: ToolCategory,
     *     tools: Collection<int, Tool>,
     *     prompts: Collection<int, PromptTemplate>,
     *     workflows: Collection<int, WorkflowRecipe>,
     *     templates: Collection<int, Template>,
     * }
     */
    private function content(string $categorySlug): array
    {
        if (! (bool) config('performance.guidance_cache.enabled', true)) {
            return $this->fetchContent($categorySlug);
        }

        $version = $this->cacheVersion->value('content');
        $ttl = max(1, (int) config('performance.guidance_cache.ttl_seconds', 300));

        return Cache::store()->remember(
            "guidance:content:v{$version}:{$categorySlug}",
            $ttl,
            fn (): array => $this->fetchContent($categorySlug),
        );
    }

    /**
     * @return array{
     *     category: ToolCategory,
     *     tools: Collection<int, Tool>,
     *     prompts: Collection<int, PromptTemplate>,
     *     workflows: Collection<int, WorkflowRecipe>,
     *     templates: Collection<int, Template>,
     * }
     */
    private function fetchContent(string $categorySlug): array
    {
        $category = ToolCategory::query()->where('slug', $categorySlug)->first();

        if (! $category instanceof ToolCategory) {
            throw new NotFoundHttpException;
        }

        $toolQuery = Tool::query()
            ->where('tools.tool_category_id', $category->getKey())
            ->with('category');
        PublishedToolVisibility::apply($toolQuery);
        $tools = $toolQuery
            ->orderByRaw('LOWER(tools.name)')
            ->orderBy('public_id')
            ->limit(self::MAX_ITEMS_PER_COLLECTION)
            ->get();

        $promptQuery = PromptTemplate::query()
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
            ->where('workflow_recipes.tool_category_id', $category->getKey())
            ->with(['category', 'steps', 'steps.tool', 'steps.promptTemplate', 'steps.template']);
        PublishedWorkflowVisibility::apply($workflowQuery);
        $workflows = $workflowQuery
            ->orderByRaw('LOWER(workflow_recipes.title)')
            ->orderBy('public_id')
            ->limit(self::MAX_ITEMS_PER_COLLECTION)
            ->get();

        $templateQuery = Template::query()
            ->where('templates.tool_category_id', $category->getKey())
            ->with(['category', 'latestVersion']);
        PublishedTemplateVisibility::apply($templateQuery);
        $templates = $templateQuery
            ->orderByRaw('LOWER(templates.title)')
            ->orderBy('public_id')
            ->limit(self::MAX_ITEMS_PER_COLLECTION)
            ->get();

        return [
            'category' => $category,
            'tools' => $tools,
            'prompts' => $prompts,
            'workflows' => $workflows,
            'templates' => $templates,
        ];
    }

    /**
     * Re-attach the current student's private viewer state to the (possibly
     * cached) shared content: one bounded lookup per relation, keyed by the
     * ≤10 ids already loaded.
     *
     * @param  array{
     *     category: ToolCategory,
     *     tools: Collection<int, Tool>,
     *     prompts: Collection<int, PromptTemplate>,
     *     workflows: Collection<int, WorkflowRecipe>,
     *     templates: Collection<int, Template>,
     * }  $content
     */
    private function applyViewerState(User $user, array $content): void
    {
        $userId = $user->getKey();

        $toolStates = DB::table('user_tool_preferences')
            ->where('user_id', $userId)
            ->whereIn('tool_id', $content['tools']->modelKeys())
            ->pluck('state', 'tool_id');
        foreach ($content['tools'] as $tool) {
            $tool->setAttribute('viewer_preference_state', $toolStates->get($tool->getKey()));
        }

        $promptIds = $content['prompts']->modelKeys();
        $promptStates = DB::table('user_prompt_preferences')
            ->where('user_id', $userId)
            ->whereIn('prompt_template_id', $promptIds)
            ->pluck('state', 'prompt_template_id');
        $promptCopies = DB::table('user_prompt_copies')
            ->where('user_id', $userId)
            ->whereIn('prompt_template_id', $promptIds)
            ->pluck('copy_count', 'prompt_template_id');
        foreach ($content['prompts'] as $prompt) {
            $prompt->setAttribute('viewer_preference_state', $promptStates->get($prompt->getKey()));
            $prompt->setAttribute('viewer_copy_count', $promptCopies->get($prompt->getKey()));
        }

        $workflowStates = DB::table('user_workflow_preferences')
            ->where('user_id', $userId)
            ->whereIn('workflow_recipe_id', $content['workflows']->modelKeys())
            ->pluck('state', 'workflow_recipe_id');
        foreach ($content['workflows'] as $workflow) {
            $workflow->setAttribute('viewer_preference_state', $workflowStates->get($workflow->getKey()));
        }

        $templateIds = $content['templates']->modelKeys();
        $templateStates = DB::table('user_template_preferences')
            ->where('user_id', $userId)
            ->whereIn('template_id', $templateIds)
            ->pluck('state', 'template_id');
        $templateCopyCounts = DB::table('user_template_copies')
            ->where('user_id', $userId)
            ->whereIn('template_id', $templateIds)
            ->whereNull('archived_at')
            ->groupBy('template_id')
            ->selectRaw('template_id, COUNT(*) as active_copies')
            ->pluck('active_copies', 'template_id');
        foreach ($content['templates'] as $template) {
            $template->setAttribute('viewer_preference_state', $templateStates->get($template->getKey()));
            $template->setAttribute('viewer_active_copy_count', (int) ($templateCopyCounts->get($template->getKey()) ?? 0));
        }
    }
}
