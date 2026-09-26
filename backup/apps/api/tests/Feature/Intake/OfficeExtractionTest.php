<?php

declare(strict_types=1);

namespace Tests\Feature\Intake;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\CompositeIntakeExtractor;
use App\Domains\Intake\Support\DocxExtractor;
use App\Domains\Intake\Support\ImageOcrExtractor;
use App\Domains\Intake\Support\PdfExtractor;
use App\Domains\Intake\Support\PlainTextExtractor;
use App\Domains\Intake\Support\PptxExtractor;
use Tests\TestCase;
use ZipArchive;

/**
 * Covers DOCX and PPTX text extraction plus the archive hardening both formats
 * share. No database is touched: these are pure content adapters.
 */
final class OfficeExtractionTest extends TestCase
{
    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const PPTX_DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const PPTX_PRESENTATION_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    private const PPTX_RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PPTX_PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const PPTX_SLIDE_REL_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';

    /** @var list<string> */
    private array $scratchFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->scratchFiles as $path) {
            @unlink($path);
        }

        $this->scratchFiles = [];

        parent::tearDown();
    }

    public function test_docx_extracts_paragraph_text_from_a_real_package(): void
    {
        $text = (new DocxExtractor)->extract($this->fixture('sample.docx'), self::DOCX_MIME);

        self::assertStringContainsString('Research Methods Syllabus', $text);
        self::assertStringContainsString('2026-08-01', $text);
        self::assertStringContainsString('Readings are listed in the appendix.', $text);

        // Paragraphs become line breaks, and no XML markup survives.
        self::assertStringNotContainsString('<w:t>', $text);
        self::assertStringNotContainsString('w:document', $text);
        self::assertSame(3, substr_count($text, "\n") + 1);
    }

    public function test_docx_output_honours_the_shared_normalisation_contract(): void
    {
        $text = (new DocxExtractor)->extract($this->fixture('sample.docx'), self::DOCX_MIME);

        self::assertSame(trim($text), $text);
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        self::assertSame(0, preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text));
        self::assertLessThanOrEqual((int) config('intake.max_extracted_characters'), mb_strlen($text));
    }

    public function test_pptx_reads_slides_in_numeric_order_not_lexical_order(): void
    {
        $text = (new PptxExtractor)->extract($this->fixture('sample.pptx'), self::PPTX_MIME);

        for ($slide = 1; $slide <= 10; $slide++) {
            self::assertStringContainsString("Slide {$slide} heading", $text);
        }

        // The whole point of numeric ordering: a lexical sort puts slide10 second.
        self::assertLessThan(
            strpos($text, 'Slide 10 heading'),
            strpos($text, 'Slide 2 heading'),
        );
        self::assertLessThan(
            strpos($text, 'Slide 3 heading'),
            strpos($text, 'Slide 2 heading'),
        );
    }

    public function test_pptx_includes_speaker_notes(): void
    {
        $text = (new PptxExtractor)->extract($this->fixture('sample.pptx'), self::PPTX_MIME);

        self::assertStringContainsString('Speaker note for the opening slide.', $text);
    }

    public function test_pptx_reads_slides_in_presentation_order_over_filename_numbering(): void
    {
        // The file numbering and the shown order deliberately disagree:
        // slide1.xml is presented second and slide2.xml first. A reader that
        // trusts the filename number alone would emit Alpha before Bravo.
        $presentation = '<p:presentation xmlns:p="'.self::PPTX_PRESENTATION_NS.'" xmlns:r="'.self::PPTX_RELATIONSHIP_NS.'">'
            .'<p:sldIdLst>'
            .'<p:sldId id="256" r:id="rId1"/>'
            .'<p:sldId id="257" r:id="rId2"/>'
            .'</p:sldIdLst></p:presentation>';

        $rels = '<Relationships xmlns="'.self::PPTX_PACKAGE_REL_NS.'">'
            .'<Relationship Id="rId1" Type="'.self::PPTX_SLIDE_REL_TYPE.'" Target="slides/slide2.xml"/>'
            .'<Relationship Id="rId2" Type="'.self::PPTX_SLIDE_REL_TYPE.'" Target="slides/slide1.xml"/>'
            .'</Relationships>';

        $archive = $this->buildArchive([
            'ppt/slides/slide1.xml' => $this->pptxSlide('Alpha slide'),
            'ppt/slides/slide2.xml' => $this->pptxSlide('Bravo slide'),
            'ppt/presentation.xml' => $presentation,
            'ppt/_rels/presentation.xml.rels' => $rels,
        ]);

        $text = (new PptxExtractor)->extract($archive, self::PPTX_MIME);

        $alpha = strpos($text, 'Alpha slide');
        $bravo = strpos($text, 'Bravo slide');
        self::assertIsInt($alpha);
        self::assertIsInt($bravo);
        self::assertLessThan($alpha, $bravo, 'presentation order must place slide2 before slide1');
    }

    public function test_pptx_falls_back_to_numeric_filename_order_without_a_presentation_part(): void
    {
        // No presentation part and no relationships: the reader cannot know the
        // shown order, so it falls back to numeric filename order rather than
        // failing the deck.
        $archive = $this->buildArchive([
            'ppt/slides/slide1.xml' => $this->pptxSlide('Alpha slide'),
            'ppt/slides/slide2.xml' => $this->pptxSlide('Bravo slide'),
        ]);

        $text = (new PptxExtractor)->extract($archive, self::PPTX_MIME);

        $alpha = strpos($text, 'Alpha slide');
        $bravo = strpos($text, 'Bravo slide');
        self::assertIsInt($alpha);
        self::assertIsInt($bravo);
        self::assertLessThan($bravo, $alpha, 'without a presentation part, numeric filename order stands');
    }

    public function test_extractors_declare_only_their_own_mime_type(): void
    {
        $docx = new DocxExtractor;
        $pptx = new PptxExtractor;

        self::assertTrue($docx->supports(self::DOCX_MIME));
        self::assertTrue($docx->supports(strtoupper(self::DOCX_MIME)));
        self::assertFalse($docx->supports(self::PPTX_MIME));
        self::assertTrue($pptx->supports(self::PPTX_MIME));
        self::assertFalse($pptx->supports('application/pdf'));
    }

    public function test_wrong_content_type_is_rejected_before_any_archive_work(): void
    {
        $this->assertFailsWith(
            IntakeFailureCode::UnsupportedContentType,
            fn (): string => (new DocxExtractor)->extract($this->fixture('sample.docx'), 'application/pdf'),
        );
    }

    public function test_composite_extractor_routes_both_office_types(): void
    {
        $composite = new CompositeIntakeExtractor(
            new PlainTextExtractor,
            new PdfExtractor,
            new DocxExtractor,
            new PptxExtractor,
            new ImageOcrExtractor,
        );

        self::assertTrue($composite->supports(self::DOCX_MIME));
        self::assertTrue($composite->supports(self::PPTX_MIME));
        self::assertStringContainsString(
            'Research Methods Syllabus',
            $composite->extract($this->fixture('sample.docx'), self::DOCX_MIME),
        );
        self::assertStringContainsString(
            'Slide 1 heading',
            $composite->extract($this->fixture('sample.pptx'), self::PPTX_MIME),
        );
    }

    public function test_zip_bomb_is_rejected_before_any_entry_is_inflated(): void
    {
        // 6 MB of a single repeated byte deflates to a few kilobytes. The guard
        // must fire on the declared uncompressed size, not on the archive size.
        $payload = str_repeat('A', 6 * 1024 * 1024);
        $archive = $this->buildArchive(['word/document.xml' => $payload]);

        self::assertLessThan(64 * 1024, strlen($archive), 'the fixture is not actually compressed');

        config()->set('intake.office.max_uncompressed_bytes', 1024 * 1024);

        $this->assertFailsWith(
            IntakeFailureCode::ContentTooLarge,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    public function test_entry_count_cap_is_enforced(): void
    {
        config()->set('intake.office.max_entries', 3);

        $this->assertFailsWith(
            IntakeFailureCode::ContentTooLarge,
            fn (): string => (new PptxExtractor)->extract($this->fixture('sample.pptx'), self::PPTX_MIME),
        );
    }

    public function test_xxe_payload_is_rejected_and_never_expanded(): void
    {
        $hostFile = tempnam(sys_get_temp_dir(), 'xxe_target_');
        self::assertIsString($hostFile);
        $this->scratchFiles[] = $hostFile;
        file_put_contents($hostFile, 'TOP-SECRET-CANARY');

        $documentXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE w:document [<!ENTITY xxe SYSTEM "file://'.$hostFile.'">]>'
            .'<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
            .'<w:p><w:r><w:t>&xxe;</w:t></w:r></w:p>'
            .'</w:body></w:document>';

        $archive = $this->buildArchive(['word/document.xml' => $documentXml]);

        try {
            $text = (new DocxExtractor)->extract($archive, self::DOCX_MIME);
            self::fail('the XXE payload was accepted, extracted text was: '.$text);
        } catch (IntakeAcquisitionFailure $failure) {
            self::assertSame(IntakeFailureCode::ExtractionFailed, $failure->failureCode);
            self::assertStringNotContainsString('TOP-SECRET-CANARY', $failure->getMessage());
        }
    }

    public function test_unsafe_entry_names_are_rejected(): void
    {
        $archive = $this->buildArchive([
            'word/document.xml' => '<w:document xmlns:w="'.self::WORD_NS.'"><w:body>'
                .'<w:p><w:r><w:t>Harmless</w:t></w:r></w:p></w:body></w:document>',
            '../../etc/passwd' => 'root:x:0:0',
        ]);

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    public function test_non_archive_bytes_are_rejected(): void
    {
        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract('this is plainly not a zip archive', self::DOCX_MIME),
        );
    }

    public function test_archive_without_the_main_document_part_fails_honestly(): void
    {
        $archive = $this->buildArchive(['word/styles.xml' => '<styles/>']);

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    public function test_presentation_without_slides_fails_honestly(): void
    {
        $archive = $this->buildArchive(['ppt/presentation.xml' => '<p:presentation/>']);

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new PptxExtractor)->extract($archive, self::PPTX_MIME),
        );
    }

    public function test_malformed_document_part_fails_honestly(): void
    {
        $archive = $this->buildArchive(['word/document.xml' => '<w:document><w:body>unclosed']);

        $this->assertFailsWith(
            IntakeFailureCode::ExtractionFailed,
            fn (): string => (new DocxExtractor)->extract($archive, self::DOCX_MIME),
        );
    }

    public function test_file_intakes_may_read_past_the_link_fetch_cap(): void
    {
        // A 25 MB upload must remain readable by intake. Holding file reads to the
        // 5 MB link cap made the product accept files it then refused to read.
        self::assertSame(
            (int) config('resources.max_upload_bytes'),
            (int) config('intake.max_file_read_bytes'),
        );
        self::assertGreaterThan(
            (int) config('intake.max_fetch_bytes'),
            (int) config('intake.max_file_read_bytes'),
        );
    }

    public function test_office_mime_types_are_extractable_and_uploadable(): void
    {
        $extractable = (array) config('intake.extractable_file_mime_types');
        $uploadable = (array) config('resources.allowed_mime_types');

        foreach ([self::DOCX_MIME, self::PPTX_MIME] as $mimeType) {
            self::assertContains($mimeType, $extractable);
            self::assertArrayHasKey($mimeType, $uploadable);
        }
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

    /** A minimal slide part carrying one line of DrawingML text. */
    private function pptxSlide(string $text): string
    {
        return '<p:sld xmlns:a="'.self::PPTX_DRAWING_NS.'"><a:p><a:t>'.$text.'</a:t></a:p></p:sld>';
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
