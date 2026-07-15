<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Exceptions\TemplatePersistenceFailure;
use App\Domains\Templates\Exceptions\TemplateStateConflict;
use App\Domains\Templates\Exceptions\TemplateVersionConflict;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Templates\Queries\FindOwnedTemplateCopy;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateTemplateCopyAction
{
    public function __construct(private FindOwnedTemplateCopy $copies) {}

    /** @param array{title: string, body: string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): UserTemplateCopy
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): UserTemplateCopy {
                $copy = $this->copies->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $copy);

                if ($copy->title === $data['title'] && $copy->body === $data['body']) {
                    return $copy;
                }

                if ($copy->archived_at !== null) {
                    throw new TemplateStateConflict;
                }

                if ($copy->version !== $expectedVersion) {
                    throw new TemplateVersionConflict;
                }

                $copy->forceFill([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'version' => $copy->version + 1,
                ])->save();

                return $copy->refresh()->load(['template', 'templateVersion', 'course']);
            }, 3);
        } catch (QueryException $exception) {
            throw TemplatePersistenceFailure::fromQueryException($exception, 'template.copy.update');
        }
    }
}
