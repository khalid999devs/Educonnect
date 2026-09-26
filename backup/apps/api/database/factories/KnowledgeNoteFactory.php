<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KnowledgeNote> */
final class KnowledgeNoteFactory extends Factory
{
    protected $model = KnowledgeNote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'knowledge_item_id' => null,
            'body' => 'A note about the source.',
            'version' => 1,
        ];
    }

    public function forItem(KnowledgeItem $item): static
    {
        return $this->state(fn (): array => [
            'user_id' => $item->user_id,
            'knowledge_item_id' => $item->getKey(),
        ]);
    }
}
