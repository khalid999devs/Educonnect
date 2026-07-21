<?php

declare(strict_types=1);

namespace App\Domains\Users\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Data\SessionSummary;
use App\Domains\Users\Exceptions\UserPersistenceFailure;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lists the authenticated user's own browser sessions.
 *
 * The raw session identifier is never returned. It is the bearer credential for
 * the session itself, so the API exposes a stable one-way digest instead: it is
 * enough for list keys and for the current-session flag, and useless to an
 * attacker who scrapes the response.
 */
final readonly class ListOwnSessions
{
    public const MAX_SESSIONS = 50;

    /**
     * @return list<SessionSummary>
     */
    public function execute(User $user, ?string $currentSessionId): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $currentSessionId, $ownsTransaction): array {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $rows = DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->orderByDesc('last_activity')
                    ->orderBy('id')
                    ->limit(self::MAX_SESSIONS)
                    ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
                $summaries = [];

                foreach ($rows as $row) {
                    $id = (string) $row->id;
                    $summaries[] = new SessionSummary(
                        id: self::digest($id),
                        ipAddress: is_string($row->ip_address) ? $row->ip_address : null,
                        userAgent: is_string($row->user_agent) ? mb_substr($row->user_agent, 0, 255) : null,
                        lastActivity: Carbon::createFromTimestampUTC((int) $row->last_activity),
                        isCurrent: $currentSessionId !== null && hash_equals($id, $currentSessionId),
                    );
                }

                return $summaries;
            }, 3);
        } catch (QueryException $exception) {
            throw UserPersistenceFailure::fromQueryException($exception, 'settings.sessions.list');
        }
    }

    public static function digest(string $sessionId): string
    {
        return substr(hash('sha256', 'educonnect-session:'.$sessionId), 0, 32);
    }
}
