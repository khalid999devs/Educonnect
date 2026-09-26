<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Actions;

use App\Domains\Guidance\Exceptions\GuidancePersistenceFailure;
use App\Domains\Guidance\Models\PromptTemplate;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Guidance\Queries\FindPublishedPrompt;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RecordPromptCopyAction
{
    public function __construct(private FindPublishedPrompt $prompts) {}

    public function execute(User $user, string $publicId): PromptTemplate
    {
        try {
            return DB::transaction(function () use ($user, $publicId): PromptTemplate {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $prompt = $this->prompts->execute($user, $publicId, lockForUpdate: true);

                Gate::forUser($user)->authorize('create', UserPromptCopy::class);

                $copy = UserPromptCopy::query()
                    ->where('user_id', $user->getKey())
                    ->where('prompt_template_id', $prompt->getKey())
                    ->lockForUpdate()
                    ->first();

                $copiedAt = now()->toImmutable();

                if ($copy instanceof UserPromptCopy) {
                    Gate::forUser($user)->authorize('update', $copy);
                    $copy->forceFill([
                        'copy_count' => $copy->copy_count + 1,
                        'last_copied_at' => $copiedAt,
                    ])->save();
                    $prompt->setAttribute('viewer_copy_count', $copy->copy_count);

                    return $prompt;
                }

                $copy = new UserPromptCopy;
                $copy->forceFill([
                    'user_id' => $user->getKey(),
                    'prompt_template_id' => $prompt->getKey(),
                    'copy_count' => 1,
                    'first_copied_at' => $copiedAt,
                    'last_copied_at' => $copiedAt,
                ])->save();
                $prompt->setAttribute('viewer_copy_count', 1);

                return $prompt;
            }, 3);
        } catch (QueryException $exception) {
            throw GuidancePersistenceFailure::fromQueryException($exception, 'prompt.copy.record');
        }
    }
}
