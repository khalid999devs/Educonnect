<?php

declare(strict_types=1);

namespace App\Domains\Admin\Queries;

use Illuminate\Support\Facades\DB;

/**
 * A privacy-safe operational overview for administrators: aggregate counts only,
 * never private academic content, prompts, messages, or per-user detail (doc 08,
 * ADM-004). AI-usage, job-health, and error telemetry are intentionally absent - 
 * they require a metrics substrate that is hardening-phase (28) work - so this
 * reports the counts the current schema can answer honestly.
 */
final class BuildOperationalOverview
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'users' => $this->users(),
            'content' => $this->content(),
            'community' => $this->community(),
            'mentors' => $this->mentors(),
            'audit_event_count' => DB::table('audit_events')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function users(): array
    {
        /** @var array<string, int> $byRole */
        $byRole = DB::table('roles')
            ->leftJoin('role_user', 'role_user.role_id', '=', 'roles.id')
            ->groupBy('roles.key')
            ->selectRaw('roles.key as role_key, COUNT(role_user.user_id) as total')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->role_key => (int) $row->total])
            ->all();

        return [
            'total' => DB::table('users')->count(),
            'active' => DB::table('users')->where('status', 'active')->count(),
            'suspended' => DB::table('users')->where('status', 'suspended')->count(),
            'by_role' => $byRole,
        ];
    }

    /** @return array<string, array<string, int>> */
    private function content(): array
    {
        return [
            'tools' => $this->stateCounts('tools'),
            'prompts' => $this->stateCounts('prompt_templates'),
            'workflows' => $this->stateCounts('workflow_recipes'),
            'templates' => $this->stateCounts('templates'),
        ];
    }

    /** @return array<string, int> */
    private function stateCounts(string $table): array
    {
        $counts = DB::table($table)
            ->groupBy('state')
            ->selectRaw('state, COUNT(*) as total')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->state => (int) $row->total])
            ->all();

        return [
            'draft' => (int) ($counts['draft'] ?? 0),
            'in_review' => (int) ($counts['in_review'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
            'archived' => (int) ($counts['archived'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function community(): array
    {
        $reports = DB::table('content_reports')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->status => (int) $row->total])
            ->all();

        return [
            'communities' => DB::table('communities')->where('visibility', 'published')->count(),
            'memberships' => DB::table('community_memberships')->count(),
            'reports' => [
                'open' => (int) ($reports['open'] ?? 0),
                'reviewing' => (int) ($reports['reviewing'] ?? 0),
                'actioned' => (int) ($reports['actioned'] ?? 0),
                'dismissed' => (int) ($reports['dismissed'] ?? 0),
            ],
        ];
    }

    /** @return array<string, int> */
    private function mentors(): array
    {
        $counts = DB::table('mentor_profiles')
            ->groupBy('verification_state')
            ->selectRaw('verification_state, COUNT(*) as total')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->verification_state => (int) $row->total])
            ->all();

        return [
            'verified' => (int) ($counts['verified'] ?? 0),
            'unverified' => (int) ($counts['unverified'] ?? 0),
        ];
    }
}
