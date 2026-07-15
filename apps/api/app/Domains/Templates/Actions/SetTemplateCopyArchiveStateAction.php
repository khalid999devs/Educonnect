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

final readonly class SetTemplateCopyArchiveStateAction
{
    public function __construct(private FindOwnedTemplateCopy $copies) {}

    public function execute(User $user, string $publicId, bool $archived, int $expectedVersion): UserTemplateCopy
    {
        $operation = $archived ? 'template.copy.archive' : 'template.copy.restore';

        try {
            return DB::transaction(function () use ($user, $publicId, $archived, $expectedVersion): UserTemplateCopy {
                $copy = $this->copies->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $copy);
                $alreadyDesired = $archived ? $copy->archived_at !== null : $copy->archived_at === null;

                if ($alreadyDesired) {
                    return $copy;
                }

                if ($copy->version !== $expectedVersion) {
                    throw new TemplateVersionConflict;
                }

                $copy->forceFill([
                    'archived_at' => $archived ? now() : null,
                    'version' => $copy->version + 1,
                ])->save();

                return $copy->refresh()->load(['template', 'templateVersion', 'course']);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw new TemplateStateConflict;
            }

            throw TemplatePersistenceFailure::fromQueryException($exception, $operation);
        }
    }
}
