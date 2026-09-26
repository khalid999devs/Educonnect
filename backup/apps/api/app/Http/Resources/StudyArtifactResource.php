<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Study\Enums\StudyArtifactStatus;
use App\Domains\Study\Models\StudyArtifact;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The client-facing shape of one study artifact.
 *
 * `payload` is present only on a `ready` artifact and `failure_reason` only on
 * a `failed` one, which mirrors the database CHECKs exactly: there is no
 * response in which a failed generation can appear to carry study material.
 *
 * @mixin StudyArtifact
 */
final class StudyArtifactResource extends JsonResource
{
    public const DISCLAIMER = 'AI-generated study material. Check it against the source before you rely on it.';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $payload = $this->getAttribute('payload');

        return [
            'id' => (string) $this->public_id,
            'kind' => $this->kind->value,
            'status' => $status->value,
            'version' => (int) $this->version,
            'is_pending' => $status->isPending(),
            'payload' => $status === StudyArtifactStatus::Ready && is_array($payload) ? $payload : null,
            'failure_reason' => $status === StudyArtifactStatus::Failed ? $this->failure_reason : null,
            'schema_version' => (string) $this->schema_version,
            'provider' => $this->provider,
            'model' => $this->model,
            'latency_ms' => $this->latency_ms,
            'disclaimer' => self::DISCLAIMER,
            'created_at' => $this->timestamp($this->getAttribute('created_at')),
            'updated_at' => $this->timestamp($this->getAttribute('updated_at')),
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
