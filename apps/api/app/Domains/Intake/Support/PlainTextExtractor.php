<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;

final class PlainTextExtractor implements IntakeContentExtractor
{
    use NormalizesExtractedText;

    private const SUPPORTED = [
        'text/plain',
        'text/markdown',
        'text/html',
        'application/xhtml+xml',
    ];

    public function supports(string $contentType): bool
    {
        return in_array(strtolower($contentType), self::SUPPORTED, true);
    }

    public function extract(string $rawContent, string $contentType): string
    {
        if (! $this->supports($contentType)) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::UnsupportedContentType,
                'no extractor supports this content type',
            );
        }

        $text = $rawContent;

        if (in_array(strtolower($contentType), ['text/html', 'application/xhtml+xml'], true)) {
            $text = preg_replace('/<(script|style|template|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $text) ?? '';
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->normalizeExtractedText($text);
    }
}
