<?php

declare(strict_types=1);

namespace App\Domains\Intake\Actions;

use App\Domains\Intake\Enums\IntakeSourceType;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\IntakeEventRecorder;
use App\Domains\Resources\Enums\ResourceKind;
use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Queries\FindOwnedResource;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateFileIntakeAction
{
    public function __construct(
        private FindOwnedResource $resources,
        private IntakeEventRecorder $events,
    ) {}

    public function execute(User $user, string $resourcePublicId, ?string $context): IntakeItem
    {
        Gate::forUser($user)->authorize('create', IntakeItem::class);

        $resource = $this->resources->execute($user, $resourcePublicId);
        $storedFile = $resource->storedFile;

        if ($resource->kind !== ResourceKind::File
            || ! $storedFile instanceof StoredFile
            || $storedFile->status !== StoredFileStatus::Ready) {
            throw ValidationException::withMessages([
                'resource_id' => ['Only an uploaded, ready file resource can be ingested.'],
            ]);
        }

        $mimeType = $storedFile->verified_mime_type ?? $storedFile->declared_mime_type;
        $extractable = config('intake.extractable_file_mime_types');

        if (! is_array($extractable) || ! in_array($mimeType, $extractable, true)) {
            throw ValidationException::withMessages([
                'resource_id' => ['This file type is not supported for intake extraction yet.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $resource, $context): IntakeItem {
                $item = new IntakeItem;
                $item->forceFill([
                    'user_id' => $user->getKey(),
                    'source_type' => IntakeSourceType::File->value,
                    'resource_id' => $resource->getKey(),
                    'context' => $context,
                    'state' => IntakeState::UploadedOrLinked->value,
                ])->save();

                $this->events->record($item, 'created', null, IntakeState::UploadedOrLinked, 'file intake created');

                $item->forceFill([
                    'state' => IntakeState::Queued->value,
                    'queued_at' => now(),
                ])->save();
                $this->events->record($item, 'queued', IntakeState::UploadedOrLinked, IntakeState::Queued);

                ProcessIntakeItem::dispatch((int) $item->getKey())->afterCommit();

                return $item->refresh()->load(['resource', 'artifacts', 'events']);
            }, 3);
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.create');
        }
    }
}
