<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Intake\Data\IntakeExtractionWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IntakeExtractionWindow */
final class IntakeExtractionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->itemPublicId,
            'has_extraction' => $this->hasExtraction,
            'content_type' => $this->contentType,
            'text' => $this->text,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'returned_characters' => $this->returnedCharacters(),
            'total_characters' => $this->totalCharacters,
            'has_more' => $this->hasMore(),
            'next_offset' => $this->nextOffset(),
        ];
    }
}
