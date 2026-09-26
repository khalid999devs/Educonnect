<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Templates\Support\PublishedTemplateVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindPublishedTemplate
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Template
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Template::query()
                ->select('templates.*')
                ->addSelect([
                    'viewer_preference_state' => UserTemplatePreference::query()
                        ->select('state')
                        ->whereColumn('user_template_preferences.template_id', 'templates.id')
                        ->where('user_template_preferences.user_id', $user->getKey())
                        ->limit(1),
                    'viewer_active_copy_count' => UserTemplateCopy::query()
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('user_template_copies.template_id', 'templates.id')
                        ->where('user_template_copies.user_id', $user->getKey())
                        ->whereNull('user_template_copies.archived_at'),
                ])
                ->where('templates.public_id', $publicId)
                ->with(['category', 'latestVersion']);

            PublishedTemplateVisibility::apply($query);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $template = $query->first();

            if (! $template instanceof Template) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $template);

            return $template;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.read');
        }
    }
}
