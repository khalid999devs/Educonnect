<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeFailureCode: string
{
    case UnsafeUrl = 'unsafe_url';
    case LinkFetchFailed = 'link_fetch_failed';
    case LinkHttpClientError = 'link_http_client_error';
    case LinkHttpServerError = 'link_http_server_error';
    case ContentTooLarge = 'content_too_large';
    case UnsupportedContentType = 'unsupported_content_type';
    case FileUnavailable = 'file_unavailable';
    case ExtractionFailed = 'extraction_failed';
    case AttemptsExhausted = 'attempts_exhausted';
    case ClassificationFailed = 'classification_failed';
    case Interrupted = 'interrupted';
    case StrandedTimeout = 'stranded_timeout';

    public function isRetryable(): bool
    {
        return in_array($this, [
            self::LinkFetchFailed,
            self::LinkHttpServerError,
            self::FileUnavailable,
            self::ExtractionFailed,
            self::ClassificationFailed,
            self::Interrupted,
            self::StrandedTimeout,
        ], true);
    }
}
