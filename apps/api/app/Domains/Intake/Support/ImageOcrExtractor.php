<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Optical character recognition for image resources via the Tesseract engine.
 * Raw bytes are written to a scratch file, recognised out-of-process, and the
 * temporary file is always removed. Text-free images fail honestly.
 */
final class ImageOcrExtractor implements IntakeContentExtractor
{
    use NormalizesExtractedText;

    private const SUPPORTED = ['image/png', 'image/jpeg', 'image/webp'];

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

        $binary = (string) config('intake.ocr.binary', 'tesseract');
        $language = (string) config('intake.ocr.languages', 'eng');
        $timeout = (int) config('intake.ocr.timeout_seconds', 30);

        $scratch = tempnam(sys_get_temp_dir(), 'intake_ocr_');

        if ($scratch === false) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'could not allocate a scratch file for OCR',
            );
        }

        try {
            file_put_contents($scratch, $rawContent);

            $result = Process::timeout($timeout)
                ->run([$binary, $scratch, 'stdout', '-l', $language]);

            if (! $result->successful()) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'optical character recognition could not read this image',
                );
            }

            $text = $result->output();
        } catch (IntakeAcquisitionFailure $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'optical character recognition could not read this image',
            );
        } finally {
            @unlink($scratch);
        }

        return $this->normalizeExtractedText($text);
    }
}
