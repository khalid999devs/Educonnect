<?php

declare(strict_types=1);

namespace Tests\Feature\SecondBrain;

use App\Domains\SecondBrain\Models\Collection;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\SecondBrain\Models\KnowledgeTag;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AssertsApiResponses;
use Tests\Feature\SecondBrain\Concerns\InteractsWithSecondBrain;
use Tests\TestCase;

final class KnowledgeSearchTest extends TestCase
{
    use AssertsApiResponses;
    use InteractsWithSecondBrain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
    }

    public function test_search_matches_title_summary_authors_and_venue_content(): void
    {
        $user = User::factory()->create();
        KnowledgeItem::factory()->for($user, 'user')->create([
            'title' => 'Attention Is All You Need',
            'summary' => 'The transformer removes recurrence entirely.',
        ]);
        KnowledgeItem::factory()->for($user, 'user')->create([
            'title' => 'Photosynthesis basics',
            'summary' => 'Light reactions in chloroplasts.',
            'authors' => 'Calvin, M.',
        ]);
        KnowledgeItem::factory()->for($user, 'user')->create([
            'title' => 'Unrelated cooking notes',
        ]);

        $this->actingAs($user, 'web');

        self::assertSame(
            ['Attention Is All You Need'],
            $this->searchTitles('transformer'),
            'Summary words should match through the search vector.',
        );
        self::assertSame(
            ['Photosynthesis basics'],
            $this->searchTitles('calvin'),
            'Author names should match through the search vector.',
        );
        self::assertSame(
            ['Attention Is All You Need'],
            $this->searchTitles('atten'),
            'Title prefixes should match through the fallback pattern.',
        );
        self::assertSame([], $this->searchTitles('quantum'));
    }

    public function test_search_is_scoped_to_the_requesting_user(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        KnowledgeItem::factory()->for($stranger, 'user')->create([
            'title' => 'Secret transformer notes',
        ]);

        $this->actingAs($user, 'web');
        self::assertSame([], $this->searchTitles('transformer'));
    }

    public function test_filters_narrow_by_collection_tag_and_source_type(): void
    {
        $user = User::factory()->create();
        $inCollection = KnowledgeItem::factory()->for($user, 'user')->create(['title' => 'Collected paper']);
        $tagged = KnowledgeItem::factory()->for($user, 'user')->linkSource()->create(['title' => 'Tagged link']);

        $collection = Collection::factory()->for($user, 'user')->create();
        DB::table('collection_knowledge_items')->insert([
            'user_id' => $user->getKey(),
            'collection_id' => $collection->getKey(),
            'knowledge_item_id' => $inCollection->getKey(),
        ]);

        $tag = KnowledgeTag::factory()->for($user, 'user')->create(['name' => 'ml']);
        DB::table('knowledge_item_tags')->insert([
            'user_id' => $user->getKey(),
            'knowledge_item_id' => $tagged->getKey(),
            'knowledge_tag_id' => $tag->getKey(),
        ]);

        $this->actingAs($user, 'web');

        $byCollection = $this->list("collection_id={$collection->public_id}");
        self::assertSame(['Collected paper'], $this->titles($byCollection));

        $byTag = $this->list('tag=ML');
        self::assertSame(['Tagged link'], $this->titles($byTag));

        $bySource = $this->list('source_type=link');
        self::assertSame(['Tagged link'], $this->titles($bySource));
    }

    /** @return list<string> */
    private function searchTitles(string $query): array
    {
        return $this->titles($this->list('search='.urlencode($query)));
    }

    private function list(string $queryString): TestResponse
    {
        return $this->withHeaders($this->headers())
            ->getJson('/api/v1/knowledge?'.$queryString)
            ->assertOk();
    }

    /** @return list<string> */
    private function titles(TestResponse $response): array
    {
        $titles = array_map(
            static fn (array $item): string => (string) $item['title'],
            $response->json('data'),
        );
        sort($titles);

        return $titles;
    }
}
