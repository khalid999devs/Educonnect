<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Serialises the authoritative progress read model.
 *
 * PRODUCT INVARIANT: this resource emits real counts only. It never adds a
 * streak, a consecutive-day run, a badge, a percentile, or a delta against
 * another period. If a field like that is ever needed, the answer is no.
 */
final class ProgressResource extends JsonResource
{
    /** The longest daily series the API will ever return in one payload. */
    private const MAX_DAILY_ENTRIES = 92;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $progress = $this->progress();

        /** @var array<string, mixed> $timeframe */
        $timeframe = $progress['timeframe'];
        /** @var array<string, int> $totals */
        $totals = $progress['totals'];
        /** @var list<array<string, mixed>> $daily */
        $daily = $progress['daily'];
        /** @var array<string, mixed> $rhythm */
        $rhythm = $progress['activity_rhythm'];
        /** @var list<array<string, mixed>> $rhythmDays */
        $rhythmDays = $rhythm['days'];
        /** @var array<string, mixed>|null $nextAction */
        $nextAction = $progress['next_action'];

        return [
            'timeframe' => [
                'timezone' => (string) $timeframe['timezone'],
                'window' => (string) $timeframe['window'],
                'starts_on' => (string) $timeframe['starts_on'],
                'ends_on' => (string) $timeframe['ends_on'],
                'term_label' => $timeframe['term_label'] === null ? null : (string) $timeframe['term_label'],
            ],
            'has_activity' => (bool) $progress['has_activity'],
            'summary' => (string) $progress['summary'],
            'totals' => [
                'tasks_completed' => (int) $totals['tasks_completed'],
                'tasks_due' => (int) $totals['tasks_due'],
                'focus_minutes' => (int) $totals['focus_minutes'],
                'resources_added' => (int) $totals['resources_added'],
                'intake_items_processed' => (int) $totals['intake_items_processed'],
                'knowledge_items_added' => (int) $totals['knowledge_items_added'],
                'notes_written' => (int) $totals['notes_written'],
                'template_copies_created' => (int) $totals['template_copies_created'],
                'research_sources_reviewed' => (int) $totals['research_sources_reviewed'],
            ],
            'daily' => array_map(
                static fn (array $day): array => [
                    'date' => (string) $day['date'],
                    'tasks_completed' => (int) $day['tasks_completed'],
                    'focus_minutes' => (int) $day['focus_minutes'],
                    'resources_added' => (int) $day['resources_added'],
                    'notes_written' => (int) $day['notes_written'],
                ],
                array_slice($daily, -self::MAX_DAILY_ENTRIES),
            ),
            'activity_rhythm' => [
                'has_activity' => (bool) $rhythm['has_activity'],
                'days' => array_map(
                    static function (array $day): array {
                        /** @var array<string, int> $signals */
                        $signals = $day['signals'];

                        return [
                            'date' => (string) $day['date'],
                            'was_active' => (bool) $day['was_active'],
                            'signals' => [
                                'tasks' => (int) $signals['tasks'],
                                'focus_minutes' => (int) $signals['focus_minutes'],
                                'resources' => (int) $signals['resources'],
                                'notes' => (int) $signals['notes'],
                            ],
                        ];
                    },
                    $rhythmDays,
                ),
            ],
            'next_action' => $nextAction === null ? null : [
                'kind' => (string) $nextAction['kind'],
                'id' => (string) $nextAction['id'],
                'title' => (string) $nextAction['title'],
                'due_at' => $nextAction['due_at'] === null ? null : (string) $nextAction['due_at'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function progress(): array
    {
        if (! is_array($this->resource)) {
            throw new LogicException('The progress resource requires a progress overview payload.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return $payload;
    }
}
