<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Contracts\PurposeRouter;
use App\Domains\Intake\Enums\IntakePurpose;

/**
 * Deterministic non-AI purpose router. It scores file extension, MIME type,
 * source URL, the student's own capture note, and academic keywords in the
 * extracted text, and it always answers: every input maps to a purpose and the
 * default is `resource`.
 *
 * It makes no network call, so it is the always-available fallback for the
 * routing agent and the sole implementation when no provider key is configured.
 * Because it only pattern-matches and never interprets, instructions embedded
 * in a captured document are inert here.
 */
final class RulePurposeRouter implements PurposeRouter
{
    /**
     * Keyword evidence per purpose. Weights are small integers on purpose: no
     * single word decides a route, and the strongest signals (an exam paper, a
     * DOI) need to out-score the broad ones.
     *
     * @var array<string, array<string, int>>
     */
    private const KEYWORDS = [
        'exam' => [
            'past paper' => 4,
            'question paper' => 4,
            'practice exam' => 4,
            'mock exam' => 4,
            'sample questions' => 3,
            'model answer' => 3,
            'marking scheme' => 3,
            'exam' => 2,
            'midterm' => 2,
            'quiz' => 2,
            'revision' => 2,
            'multiple choice' => 2,
        ],
        'research' => [
            'literature review' => 4,
            'systematic review' => 4,
            'related work' => 3,
            'methodology' => 3,
            'preprint' => 3,
            'abstract' => 2,
            'doi' => 2,
            'arxiv' => 2,
            'journal' => 2,
            'citation' => 2,
            'references' => 1,
            'et al' => 2,
        ],
        'study' => [
            'lecture' => 3,
            'lesson' => 2,
            'course notes' => 3,
            'study guide' => 4,
            'syllabus' => 3,
            'textbook' => 3,
            'chapter' => 2,
            'tutorial' => 2,
            'handout' => 2,
            'worksheet' => 2,
            'slides' => 2,
            'curriculum' => 2,
        ],
    ];

    /**
     * Host fragments that are strong research evidence on their own.
     *
     * @var list<string>
     */
    private const RESEARCH_URL_FRAGMENTS = [
        'arxiv.org', 'doi.org', 'pubmed', 'ncbi.nlm.nih.gov', 'jstor.org',
        'sciencedirect.com', 'springer.com', 'ieeexplore.ieee.org',
        'dl.acm.org', 'scholar.google', 'researchgate.net', 'ssrn.com',
    ];

    /**
     * Extension and MIME evidence. A slide deck is study material far more
     * often than it is anything else; a plain document carries no signal.
     *
     * @var array<string, array{purpose: string, weight: int}>
     */
    private const FILE_SIGNALS = [
        'ppt' => ['purpose' => 'study', 'weight' => 3],
        'pptx' => ['purpose' => 'study', 'weight' => 3],
        'key' => ['purpose' => 'study', 'weight' => 3],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => [
            'purpose' => 'study', 'weight' => 3,
        ],
        'application/vnd.ms-powerpoint' => ['purpose' => 'study', 'weight' => 3],
    ];

    /**
     * Only the first slice of extracted text is scored. Purpose evidence is
     * front-loaded (titles, headings, abstracts) and reading megabytes of body
     * text to route a four-way choice buys nothing.
     */
    private const SCORED_CHARACTERS = 4_000;

    public function name(): string
    {
        return 'rule_based';
    }

    public function model(): string
    {
        return 'deterministic-purpose-rules-1';
    }

    public function route(PurposeRoutingRequest $request): IntakePurpose
    {
        $scores = ['exam' => 0, 'research' => 0, 'study' => 0];

        $haystack = mb_strtolower(implode("\n", array_filter([
            $request->fileName,
            $request->context,
            $request->sourceUrl,
            mb_substr($request->extractedText, 0, self::SCORED_CHARACTERS),
        ], static fn (?string $part): bool => $part !== null && $part !== '')));

        foreach (self::KEYWORDS as $purpose => $keywords) {
            foreach ($keywords as $keyword => $weight) {
                // Whole-word matching, not substring: "example" is not an exam,
                // "doing" is not a DOI, and "budget allocation" is not "et al".
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $haystack) === 1) {
                    $scores[$purpose] += $weight;
                }
            }
        }

        $scores['research'] += $this->researchUrlWeight($request->sourceUrl);

        foreach ($this->fileTokens($request) as $token) {
            $signal = self::FILE_SIGNALS[$token] ?? null;

            if ($signal !== null) {
                $scores[$signal['purpose']] += $signal['weight'];
            }
        }

        // Ties break in a fixed order rather than by array order, so the same
        // evidence always produces the same route on every machine and run.
        foreach (['exam', 'research', 'study'] as $purpose) {
            if ($scores[$purpose] === max($scores) && $scores[$purpose] > 0) {
                return IntakePurpose::from($purpose);
            }
        }

        return IntakePurpose::default();
    }

    private function researchUrlWeight(?string $url): int
    {
        if ($url === null || $url === '') {
            return 0;
        }

        $lower = mb_strtolower($url);

        foreach (self::RESEARCH_URL_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return 4;
            }
        }

        return 0;
    }

    /** @return list<string> */
    private function fileTokens(PurposeRoutingRequest $request): array
    {
        $tokens = [];

        if ($request->mimeType !== null && $request->mimeType !== '') {
            $tokens[] = mb_strtolower($request->mimeType);
        }

        if ($request->fileName !== null && $request->fileName !== '') {
            $extension = pathinfo($request->fileName, PATHINFO_EXTENSION);

            if ($extension !== '') {
                $tokens[] = mb_strtolower($extension);
            }
        }

        return $tokens;
    }
}
