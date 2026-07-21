<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Jobs\ProcessIntakeItem;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;
use App\Domains\Intake\Support\DocxExtractor;
use App\Domains\Intake\Support\PlainTextExtractor;
use App\Domains\Intake\Support\PptxExtractor;
use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeResourceUploadSigner;
use Tests\Feature\Intake\Concerns\InteractsWithIntake;
use Tests\TestCase;
use ZipArchive;

/**
 * Proves the whole DOCX/PPTX path, not just the extractor in isolation:
 * upload -> confirm -> intake -> extract -> read, over genuine OOXML packages,
 * plus the two database MIME allowlists, the archive hardening guards, the
 * file-versus-link size caps, and the shared normalisation contract.
 *
 * `OfficeExtractionTest` covers the extractors as units. This suite exists to
 * catch the failures that only appear when the pieces are wired together: a
 * MIME type the config accepts but a CHECK constraint refuses, or an upload
 * limit the intake reader will not honour.
 */
final class OfficeIntakeEndToEndTest extends TestCase
{
    use InteractsWithIntake;
    use RefreshDatabase;

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @var list<string> */
    private array $scratchFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBrowserBoundary();
        config()->set('resources.disk', 's3');
        Storage::fake('s3');
        $this->app->instance(ResourceUploadSigner::class, new FakeResourceUploadSigner);
    }

    protected function tearDown(): void
    {
        foreach ($this->scratchFiles as $path) {
            @unlink($path);
        }

        $this->scratchFiles = [];

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // 1. DOCX, end to end
    // ------------------------------------------------------------------

    public function test_a_genuine_docx_travels_from_upload_through_intake_to_readable_text(): void
    {
        Queue::fake();
        $bytes = $this->fixture('sample.docx');
        $this->assertIsCompleteOfficePackage($bytes, 'word/document.xml');

        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $resourceId = $this->uploadAndConfirm($user, 'lecture-notes.docx', self::DOCX_MIME, $bytes);
        $item = $this->startIntake($resourceId);
        $this->app->call([new ProcessIntakeItem((int) $item->getKey()), 'handle']);

        $item->refresh();
        self::assertSame('extracted', $item->state->value);
        self::assertNull($item->failure_code);

        $acquired = $item->artifacts()
            ->where('kind', IntakeArtifactKind::AcquiredContent->value)
            ->firstOrFail();
        self::assertSame(self::DOCX_MIME, $acquired->content_type);
        self::assertSame(strlen($bytes), $acquired->byte_size);

        $text = $this->readExtraction($item);
        self::assertStringContainsString('Research Methods Syllabus', $text);
        self::assertStringContainsString('Assignment due 2026-08-01.', $text);
        self::assertStringContainsString('Readings are listed in the appendix.', $text);
        self::assertStringNotContainsString('<w:t>', $text);
        self::assertStringNotContainsString('PK', $text);
    }

    // ------------------------------------------------------------------
    // 2. PPTX, end to end, numeric slide order and speaker notes
    // ------------------------------------------------------------------

    public function test_a_genuine_pptx_reaches_intake_with_numeric_slide_order_and_speaker_notes(): void
    {
        Queue::fake();
        $bytes = $this->fixture('sample.pptx');
        $this->assertIsCompleteOfficePackage($bytes, 'ppt/slides/slide1.xml');

        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $resourceId = $this->uploadAndConfirm($user, 'week-04-deck.pptx', self::PPTX_MIME, $bytes);
        $item = $this->startIntake($resourceId);
        $this->app->call([new ProcessIntakeItem((int) $item->getKey()), 'handle']);

        $item->refresh();
        self::assertSame('extracted', $item->state->value);

        $text = $this->readExtraction($item);

        $positions = [];

        for ($slide = 1; $slide <= 10; $slide++) {
            $position = strpos($text, "Slide {$slide} heading");
            self::assertIsInt($position, "slide {$slide} is missing from the extracted deck");
            $positions[$slide] = $position;
        }

        // A lexical sort of slide*.xml puts slide10 immediately after slide1,
        // which silently reorders the deck. Every neighbouring pair must be
        // ascending for the read to be numerically ordered.
        for ($slide = 1; $slide < 10; $slide++) {
            self::assertLessThan(
                $positions[$slide + 1],
                $positions[$slide],
                "slide {$slide} must precede slide ".($slide + 1),
            );
        }

        self::assertStringContainsString('Speaker note for the opening slide.', $text);
        $notePosition = strpos($text, 'Speaker note for the opening slide.');
        self::assertIsInt($notePosition);
        self::assertGreaterThan(
            $positions[10],
            $notePosition,
            'notes are appended after the slides, never interleaved',
        );
    }

    // ------------------------------------------------------------------
    // 3. The two stored_files MIME CHECK constraints
    // ------------------------------------------------------------------

    public function test_both_stored_file_mime_checks_accept_ooxml_and_still_reject_an_unlisted_type(): void
    {
        foreach ([self::DOCX_MIME, self::PPTX_MIME] as $mimeType) {
            self::assertTrue($this->constraintExists('stored_files_declared_mime_type_allowed'));
            self::assertTrue($this->constraintExists('stored_files_verified_mime_type_allowed'));

            // Writing a ready row exercises both allowlists at once: `declared`
            // and `verified` are populated in the same INSERT.
            $resource = Resource::factory()->file()->create();
            $file = StoredFile::factory()
                ->forResource($resource)
                ->state(['declared_mime_type' => $mimeType])
                ->ready()
                ->create();

            self::assertSame($mimeType, $file->declared_mime_type);
            self::assertSame($mimeType, $file->verified_mime_type);

            /* The allowlists are asserted on INSERT, not UPDATE. The upload
               contract columns are immutable after creation: the
               educonnect_enforce_stored_file_transition trigger rejects the
               UPDATE outright, which would mask the CHECK being tested. */
            $this->assertCheckViolation(
                'stored_files_declared_mime_type_allowed',
                fn () => StoredFile::factory()
                    ->forResource(Resource::factory()->file()->create())
                    ->state(['declared_mime_type' => 'application/zip'])
                    ->create(),
            );

            $this->assertCheckViolation(
                'stored_files_verified_mime_type_allowed',
                fn () => StoredFile::factory()
                    ->forResource(Resource::factory()->file()->create())
                    ->state(['declared_mime_type' => $mimeType])
                    ->ready()
                    ->state(['verified_mime_type' => 'application/x-tar'])
                    ->create(),
            );
        }
    }

    // ------------------------------------------------------------------
    // 4. The zip-bomb guard fires before any entry is read
    // ------------------------------------------------------------------

    public function test_the_zip_bomb_guard_fires_before_a_single_entry_is_read(): void
    {
        // The archive deliberately omits `word/document.xml`. Without the size
        // guard the extractor reports the missing main part, so a
        // `content_too_large` verdict can only mean the central-directory scan
        // ran first and nothing was inflated.
        $archive = $this->buildArchive(['word/styles.xml' => str_repeat('A', 6 * 1024 * 1024)]);
        self::assertLessThan(64 * 1024, strlen($archive), 'the fixture is not actually compressed');

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );

        config()->set('intake.office.max_uncompressed_bytes', 1024 * 1024);

        $this->assertFailsWith(
            IntakeFailureCode::ContentTooLarge,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    public function test_the_entry_count_cap_is_enforced_from_the_central_directory(): void
    {
        config()->set('intake.office.max_entries', 3);

        $this->assertFailsWith(
            IntakeFailureCode::ContentTooLarge,
            fn (): string => (new PptxExtractor)->extract($this->fixture('sample.pptx'), self::PPTX_MIME),
        );
    }

    // ------------------------------------------------------------------
    // 5. XXE
    // ------------------------------------------------------------------

    public function test_an_external_entity_is_never_resolved_into_extracted_text(): void
    {
        $canaryFile = tempnam(sys_get_temp_dir(), 'ooxml_canary_');
        self::assertIsString($canaryFile);
        $this->scratchFiles[] = $canaryFile;
        file_put_contents($canaryFile, 'TOP-SECRET-CANARY');

        $documentXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE w:document [<!ENTITY xxe SYSTEM "file://'.$canaryFile.'">]>'
            .'<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
            .'<w:p><w:r><w:t>&xxe;</w:t></w:r></w:p>'
            .'</w:body></w:document>';

        try {
            $text = (new DocxExtractor)->extract(
                $this->buildArchive(['word/document.xml' => $documentXml]),
                self::DOCX_MIME,
            );

            self::fail('the XXE payload was accepted; extracted text was: '.$text);
        } catch (IntakeAcquisitionFailure $failure) {
            self::assertSame(IntakeFailureCode::ExtractionFailed, $failure->failureCode);
            self::assertStringNotContainsString('TOP-SECRET-CANARY', $failure->getMessage());
        }

        /* The same package without the DOCTYPE or the entity reference proves
           the rejection above is the entity guard rather than an unrelated
           parse failure. This is composed rather than regex-stripped: a
           DOCTYPE internal subset contains its own '>' characters, so a naive
           <!DOCTYPE[^>]*> pattern truncates mid-declaration and leaves a
           stray ']>' that is genuinely malformed. */
        $benign = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
            .'<w:p><w:r><w:t>Harmless heading</w:t></w:r></w:p>'
            .'</w:body></w:document>';

        self::assertStringContainsString(
            'Harmless heading',
            (new DocxExtractor)->extract($this->buildArchive(['word/document.xml' => $benign]), self::DOCX_MIME),
        );
    }

    // ------------------------------------------------------------------
    // 6. Unsafe entry names
    // ------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function unsafeEntryNameProvider(): iterable
    {
        yield 'parent traversal' => ['../../etc/passwd'];
        yield 'nested traversal' => ['word/../../../etc/shadow'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'windows absolute path' => ['\\windows\\system32\\config'];
    }

    #[DataProvider('unsafeEntryNameProvider')]
    public function test_unsafe_entry_names_are_rejected(string $entryName): void
    {
        $archive = $this->buildArchive([
            'word/document.xml' => '<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
                .'<w:p><w:r><w:t>Harmless</w:t></w:r></w:p></w:body></w:document>',
            $entryName => 'root:x:0:0',
        ]);

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    // ------------------------------------------------------------------
    // 7. The shared normalisation contract
    // ------------------------------------------------------------------

    public function test_office_output_matches_the_normalisation_contract_of_the_other_extractors(): void
    {
        $documentXml = '<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
            .'<w:p><w:r><w:t xml:space="preserve">   Heading    with   gaps   </w:t></w:r></w:p>'
            .'<w:p><w:r><w:t xml:space="preserve">Second</w:t></w:r><w:r><w:tab/></w:r>'
            .'<w:r><w:t xml:space="preserve">line   </w:t></w:r></w:p>'
            .'</w:body></w:document>';

        $office = (new DocxExtractor)->extract(
            $this->buildArchive(['word/document.xml' => $documentXml]),
            self::DOCX_MIME,
        );

        // The plain-text path is the reference implementation of the contract.
        $plain = (new PlainTextExtractor)->extract(
            "   Heading    with   gaps   \n Second \t line   ",
            'text/plain',
        );

        self::assertSame($plain, $office);
        self::assertSame("Heading with gaps\nSecond line", $office);

        // And the output is a fixed point of the shared trait, which is only
        // true if it was produced through it.
        $normalizer = new class
        {
            use NormalizesExtractedText;

            public function normalize(string $text): string
            {
                return $this->normalizeExtractedText($text);
            }
        };

        self::assertSame($office, $normalizer->normalize($office));
        self::assertSame(trim($office), $office);
        self::assertSame(0, preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $office));
        self::assertLessThanOrEqual((int) config('intake.max_extracted_characters'), mb_strlen($office));
    }

    // ------------------------------------------------------------------
    // 8. File intakes are bounded by the upload cap, never the link cap
    // ------------------------------------------------------------------

    public function test_the_intake_file_cap_tracks_the_upload_cap_and_exceeds_the_link_cap(): void
    {
        // The defect this closes: a file large enough to upload but too large
        // for the remote-fetch cap was accepted and then refused at intake.
        self::assertSame(
            (int) config('resources.max_upload_bytes'),
            (int) config('intake.max_file_read_bytes'),
        );
        self::assertGreaterThan(
            (int) config('intake.max_fetch_bytes'),
            (int) config('intake.max_file_read_bytes'),
        );
    }

    public function test_a_file_larger_than_the_link_cap_is_still_read_at_the_exact_upload_boundary(): void
    {
        Queue::fake();
        $bytes = $this->fixture('sample.docx');
        $size = strlen($bytes);

        // Bracket the payload: below the link cap, exactly at the file cap.
        // Only a reader honouring the FILE cap can succeed here.
        config()->set('intake.max_fetch_bytes', $size - 1);
        config()->set('intake.max_file_read_bytes', $size);

        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $resourceId = $this->uploadAndConfirm($user, 'boundary.docx', self::DOCX_MIME, $bytes);
        $item = $this->startIntake($resourceId);
        $this->app->call([new ProcessIntakeItem((int) $item->getKey()), 'handle']);

        $item->refresh();
        self::assertSame('extracted', $item->state->value);
        self::assertStringContainsString('Research Methods Syllabus', $this->readExtraction($item));
    }

    public function test_a_file_one_byte_over_the_file_cap_fails_as_content_too_large(): void
    {
        Queue::fake();
        $bytes = $this->fixture('sample.docx');

        config()->set('intake.max_file_read_bytes', strlen($bytes) - 1);

        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $resourceId = $this->uploadAndConfirm($user, 'over-limit.docx', self::DOCX_MIME, $bytes);
        $item = $this->startIntake($resourceId);
        $this->app->call([new ProcessIntakeItem((int) $item->getKey()), 'handle']);

        $item->refresh();
        self::assertSame('failed_final', $item->state->value);
        self::assertSame(IntakeFailureCode::ContentTooLarge, $item->failure_code);
    }

    public function test_both_office_types_are_uploadable_and_extractable_by_configuration(): void
    {
        $extractable = (array) config('intake.extractable_file_mime_types');
        $uploadable = (array) config('resources.allowed_mime_types');

        foreach ([self::DOCX_MIME => 'docx', self::PPTX_MIME => 'pptx'] as $mimeType => $extension) {
            self::assertContains($mimeType, $extractable);
            self::assertArrayHasKey($mimeType, $uploadable);
            self::assertContains($extension, (array) $uploadable[$mimeType]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function uploadAndConfirm(User $user, string $originalName, string $mimeType, string $bytes): string
    {
        $initiated = $this->withHeaders($this->headers())
            ->postJson('/api/v1/resources/files', [
                'title' => 'Office material',
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
            ])
            ->assertCreated()
            ->assertJsonPath('data.resource.file.status', 'pending');

        $resourceId = $initiated->json('data.resource.id');
        self::assertIsString($resourceId);

        $file = StoredFile::query()
            ->whereHas('resource', fn ($query) => $query->where('public_id', $resourceId))
            ->firstOrFail();
        Storage::disk('s3')->put((string) $file->upload_key, $bytes);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/resources/{$resourceId}/confirm", ['expected_version' => 1])
            ->assertOk()
            ->assertJsonPath('data.file.status', 'ready')
            ->assertJsonPath('data.file.verified_mime_type', $mimeType)
            ->assertJsonPath('data.file.verified_size', strlen($bytes));

        return $resourceId;
    }

    private function startIntake(string $resourceId): IntakeItem
    {
        $created = $this->withHeaders($this->headers())
            ->postJson('/api/v1/intake/files', ['resource_id' => $resourceId])
            ->assertCreated()
            ->assertJsonPath('data.source.type', 'file')
            ->assertJsonPath('data.state', 'queued');

        $itemId = $created->json('data.id');
        self::assertIsString($itemId);

        return IntakeItem::query()->where('public_id', $itemId)->firstOrFail();
    }

    private function readExtraction(IntakeItem $item): string
    {
        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/intake/{$item->public_id}/extraction")
            ->assertOk()
            ->assertJsonPath('data.has_extraction', true)
            ->assertJsonPath('data.content_type', 'text/plain');

        $text = $response->json('data.text');
        self::assertIsString($text);

        return $text;
    }

    /** @param callable(): mixed $operation */
    private function assertCheckViolation(string $constraint, callable $operation): void
    {
        /* Each attempt runs inside its own savepoint. RefreshDatabase already
           holds an open transaction, and a constraint violation aborts it, so
           without this every attempt after the first reports 25P02
           (in_failed_sql_transaction) instead of the 23514 being asserted. */
        DB::beginTransaction();

        try {
            $operation();
        } catch (QueryException $exception) {
            DB::rollBack();
            self::assertSame('23514', (string) $exception->getCode());
            self::assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        DB::rollBack();
        self::fail("expected {$constraint} to reject the write");
    }

    /** @param callable(): mixed $operation */
    private function assertFailsWith(IntakeFailureCode $code, callable $operation): void
    {
        try {
            $operation();
        } catch (IntakeAcquisitionFailure $failure) {
            self::assertSame($code, $failure->failureCode, 'wrong failure code: '.$failure->getMessage());

            return;
        }

        self::fail('expected an IntakeAcquisitionFailure with code '.$code->value);
    }

    private function constraintExists(string $constraint): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = CURRENT_SCHEMA() AND t.relname = ? AND c.conname = ?',
            ['stored_files', $constraint],
        ) !== null;
    }

    /**
     * A real OOXML package is an OPC container: it carries the content-type
     * part and the package relationships, not merely the one XML part the
     * extractor happens to read.
     */
    private function assertIsCompleteOfficePackage(string $bytes, string $requiredPart): void
    {
        self::assertTrue(str_starts_with($bytes, "PK\x03\x04"), 'the fixture is not a ZIP container');

        $path = tempnam(sys_get_temp_dir(), 'ooxml_probe_');
        self::assertIsString($path);
        $this->scratchFiles[] = $path;
        file_put_contents($path, $bytes);

        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::RDONLY) === true);

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            self::assertIsArray($stat);
            $names[] = (string) $stat['name'];
        }

        $zip->close();

        self::assertContains('[Content_Types].xml', $names);
        self::assertContains('_rels/.rels', $names);
        self::assertContains($requiredPart, $names);
    }

    /** @param array<string, string> $parts */
    private function buildArchive(array $parts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ooxml_fixture_');
        self::assertIsString($path);
        $this->scratchFiles[] = $path;

        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($parts as $name => $body) {
            $zip->addFromString($name, $body);
        }

        $zip->close();

        $bytes = file_get_contents($path);
        self::assertIsString($bytes);

        return $bytes;
    }

    private function fixture(string $name): string
    {
        $bytes = file_get_contents(base_path('tests/Fixtures/Intake/'.$name));
        self::assertIsString($bytes, "missing fixture {$name}");

        return $bytes;
    }
}
