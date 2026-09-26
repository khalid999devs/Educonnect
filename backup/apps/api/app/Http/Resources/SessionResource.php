<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Users\Data\SessionSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin SessionSummary */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->session();

        return [
            'id' => $session->id,
            'ip_address' => $session->ipAddress,
            'user_agent' => $session->userAgent,
            'last_activity' => $session->lastActivity->toISOString(),
            'is_current' => $session->isCurrent,
        ];
    }

    private function session(): SessionSummary
    {
        if (! $this->resource instanceof SessionSummary) {
            throw new LogicException('The session resource requires a session summary.');
        }

        return $this->resource;
    }
}
