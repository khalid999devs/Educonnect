<?php

declare(strict_types=1);

namespace App\Domains\Study\Contracts;

use App\Domains\Study\AI\StudyGenerationRequest;
use App\Domains\Study\Enums\StudyArtifactKind;

/**
 * The study-generation boundary: given bounded, untrusted document text,
 * produce validated study material for one kind.
 *
 * There is no deterministic implementation of this contract and there must
 * never be one. Study material is not an organization feature; a fabricated
 * summary or a wrong exam answer is strictly worse than none, so an
 * implementation that cannot answer throws and the artifact is recorded as
 * `failed` with an honest reason.
 */
interface StudyGenerator
{
    public function name(): string;

    public function model(StudyArtifactKind $kind): string;

    /**
     * @return array<string, mixed> the validated payload, exactly as persisted
     */
    public function generate(StudyGenerationRequest $request): array;
}
