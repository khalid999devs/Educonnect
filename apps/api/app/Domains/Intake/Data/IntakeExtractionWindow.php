<?php

declare(strict_types=1);

namespace App\Domains\Intake\Data;

/**
 * A bounded slice of an intake item's extracted text.
 *
 * Extracted text is capped at config('intake.max_extracted_characters')
 * (200,000) which must never ship in a single payload, so reads are windowed
 * and the client pages with offset/limit against totalCharacters.
 */
final readonly class IntakeExtractionWindow
{
    public function __construct(
        public string $itemPublicId,
        public bool $hasExtraction,
        public ?string $contentType,
        public string $text,
        public int $offset,
        public int $limit,
        public int $totalCharacters,
    ) {}

    public static function absent(string $itemPublicId, int $offset, int $limit): self
    {
        return new self($itemPublicId, false, null, '', $offset, $limit, 0);
    }

    public function returnedCharacters(): int
    {
        return mb_strlen($this->text);
    }

    public function hasMore(): bool
    {
        return $this->offset + $this->returnedCharacters() < $this->totalCharacters;
    }

    public function nextOffset(): ?int
    {
        return $this->hasMore() ? $this->offset + $this->returnedCharacters() : null;
    }
}
