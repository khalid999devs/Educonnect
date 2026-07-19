<?php

declare(strict_types=1);

namespace App\Domains\Copilot\Queries;

use App\Domains\Dashboard\Queries\BuildDashboard;
use App\Domains\Users\Models\User;

/**
 * Assembles the bounded message list for a Copilot turn: doc-12 guardrails,
 * a truthful workspace snapshot from the dashboard aggregate (real records
 * only), the client-held bounded history, then the user message.
 */
final readonly class BuildCopilotMessages
{
    public function __construct(private BuildDashboard $dashboard) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    public function execute(User $user, string $timezone, array $history, string $message): array
    {
        $aggregate = $this->dashboard->execute($user, $timezone);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($aggregate)],
        ];

        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    /** @param array<string, mixed> $aggregate */
    private function systemPrompt(array $aggregate): string
    {
        $context = implode("\n", $this->contextLines($aggregate));

        return <<<PROMPT
You are the EduConnect Copilot, a focused assistant inside a university student's private workspace.

You may:
- explain what the current product area shows and where features live (Dashboard, Smart Intake, Planner, Resources, AI Tools, Templates, Second Brain, Progress);
- summarize the student's own workspace snapshot below, always naming where a fact comes from (for example "from your planner");
- suggest one specific, real next action grounded in the snapshot;
- help the student phrase plans, questions, or drafts that they will apply themselves.

You must not:
- claim to have created, changed, sent, published, or deleted anything, since you cannot act, only advise;
- invent tasks, courses, deadlines, metrics, or content that are not in the snapshot; if the snapshot lacks something, say so and point to where the student can look;
- present uncertainty as certainty, or give medical, legal, or crisis guidance beyond suggesting appropriate professional help;
- help with academic dishonesty; encourage responsible, disclosed use of AI instead.

Style: concise plain text, no markdown syntax, sentence case, at most a few short paragraphs or a short dash list.

Workspace snapshot (real records at this moment):
{$context}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @return list<string>
     */
    private function contextLines(array $aggregate): array
    {
        $lines = [];
        $name = $this->stringAt($aggregate, 'cover', 'name');
        $institution = $this->stringAt($aggregate, 'cover', 'institution');
        $term = $this->stringAt($aggregate, 'cover', 'term', 'label');
        $courseCount = $this->intAt($aggregate, 'cover', 'active_course_count');

        $lines[] = sprintf(
            'Student: %s%s%s · %d active courses.',
            $name ?? 'Unknown',
            $institution !== null ? " · {$institution}" : '',
            $term !== null ? " · {$term}" : '',
            $courseCount,
        );

        $summary = $this->stringAt($aggregate, 'progress', 'summary');

        if ($summary !== null) {
            $lines[] = "Progress (from the planner): {$summary}";
        }

        $tasks = $this->listAt($aggregate, 'whats_next', 'tasks');
        $maxTasks = max(1, (int) config('ai.copilot.max_context_tasks'));

        if ($tasks === []) {
            $lines[] = 'Open dated tasks: none.';
        } else {
            $lines[] = 'Open dated tasks (from the planner):';

            foreach (array_slice($tasks, 0, $maxTasks) as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $title = is_string($task['title'] ?? null) ? $task['title'] : 'Untitled task';
                $due = is_string($task['due_at'] ?? null) ? $task['due_at'] : 'no due date';
                $course = is_array($task['course'] ?? null) && is_string($task['course']['title'] ?? null)
                    ? " · {$task['course']['title']}"
                    : '';
                $lines[] = "- {$title} (due {$due}{$course})";
            }
        }

        $lines[] = sprintf(
            'Due today: %d. Intake captures awaiting review: %d. Second Brain items: %d.',
            $this->intAt($aggregate, 'today', 'due_task_count'),
            $this->intAt($aggregate, 'quick_intake', 'awaiting_review_count'),
            $this->intAt($aggregate, 'second_brain', 'total_item_count'),
        );

        return $lines;
    }

    /** @param array<string, mixed> $aggregate */
    private function stringAt(array $aggregate, string ...$path): ?string
    {
        $value = $this->valueAt($aggregate, ...$path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $aggregate */
    private function intAt(array $aggregate, string ...$path): int
    {
        $value = $this->valueAt($aggregate, ...$path);

        return is_int($value) ? $value : 0;
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @return list<mixed>
     */
    private function listAt(array $aggregate, string ...$path): array
    {
        $value = $this->valueAt($aggregate, ...$path);

        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /** @param array<string, mixed> $aggregate */
    private function valueAt(array $aggregate, string ...$path): mixed
    {
        $value = $aggregate;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
