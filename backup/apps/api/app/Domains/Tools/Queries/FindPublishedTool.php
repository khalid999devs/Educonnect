<?php

declare(strict_types=1);

namespace App\Domains\Tools\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Exceptions\ToolPersistenceFailure;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Tools\Support\PublishedToolVisibility;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindPublishedTool
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Tool
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Tool::query()
                ->select('tools.*')
                ->addSelect([
                    'viewer_preference_state' => UserToolPreference::query()
                        ->select('state')
                        ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                        ->where('user_tool_preferences.user_id', $user->getKey())
                        ->limit(1),
                ])
                ->where('tools.public_id', $publicId)
                ->with('category');

            PublishedToolVisibility::apply($query);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $tool = $query->first();

            if (! $tool instanceof Tool) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $tool);

            return $tool;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw ToolPersistenceFailure::fromQueryException($exception, 'tool.read');
        }
    }
}
