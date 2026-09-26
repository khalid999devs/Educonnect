<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support\Concerns;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;

/**
 * Shared normalisation for extracted text: strip control characters, collapse
 * whitespace, enforce UTF-8, and bound the length. Every extractor funnels its
 * raw output through this so downstream classification sees consistent text.
 */
trait NormalizesExtractedText
{
    /**
     * @throws IntakeAcquisitionFailure when the content yields no readable text
     */
    protected function normalizeExtractedText(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);
        $text = trim($text);

        if ($text === '' || ! mb_check_encoding($text, 'UTF-8')) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'the content produced no readable text',
            );
        }

        $limit = (int) config('intake.max_extracted_characters');

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) : $text;
    }
}
