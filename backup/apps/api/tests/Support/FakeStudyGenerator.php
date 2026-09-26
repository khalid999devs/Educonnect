<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Study\AI\StudyGenerationRequest;
use App\Domains\Study\Contracts\StudyGenerator;
use App\Domains\Study\Enums\StudyArtifactKind;
use Throwable;

/**
 * A StudyGenerator test double that either answers with a fixed payload or
 * explodes, so the honest-failure path can be exercised without a network.
 *
 * It records the requests it saw, which is how the prompt-bounding and
 * ownership assertions check what actually reached a provider.
 */
final class FakeStudyGenerator implements StudyGenerator
{
    /** @var list<StudyGenerationRequest> */
    public array $requests = [];

    public int $calls = 0;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<Throwable>  $failures  thrown, in order, before $payload is returned
     */
    public function __construct(
        private readonly array $payload = [],
        private array $failures = [],
        private readonly string $name = 'fake-openai',
        private readonly string $model = 'fake-study-model',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function model(StudyArtifactKind $kind): string
    {
        return $this->model;
    }

    /** @return array<string, mixed> */
    public function generate(StudyGenerationRequest $request): array
    {
        $this->calls++;
        $this->requests[] = $request;

        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }

        return $this->payload;
    }
}
