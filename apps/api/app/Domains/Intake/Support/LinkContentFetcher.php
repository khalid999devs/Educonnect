<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Exceptions\UnsafeIntakeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bounded SSRF-safe fetcher: every hop (including each redirect) re-passes
 * the SafeIntakeUrl guard, redirects are capped, the response type must be
 * allowlisted textual content, and the body read is size-limited.
 */
final readonly class LinkContentFetcher
{
    public function __construct(private SafeIntakeUrl $safeUrl) {}

    public function fetch(string $url): FetchedContent
    {
        $maxRedirects = (int) config('intake.max_redirects');
        $currentUrl = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            try {
                $safe = $this->safeUrl->assertSafe($currentUrl);
            } catch (UnsafeIntakeUrl $exception) {
                throw new IntakeAcquisitionFailure(IntakeFailureCode::UnsafeUrl, $exception->getMessage());
            }

            $response = $this->request($safe['url']);
            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');

                if ($location === '' || $hop === $maxRedirects) {
                    throw new IntakeAcquisitionFailure(
                        IntakeFailureCode::LinkFetchFailed,
                        'redirect limit exceeded or missing location',
                    );
                }

                $currentUrl = $this->resolveRedirect($safe['url'], $location);

                continue;
            }

            if ($status >= 400 && $status < 500) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::LinkHttpClientError,
                    'the link responded with client error '.$status,
                );
            }

            if ($status >= 500) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::LinkHttpServerError,
                    'the link responded with server error '.$status,
                );
            }

            return $this->readBounded($safe['url'], $response);
        }

        throw new IntakeAcquisitionFailure(IntakeFailureCode::LinkFetchFailed, 'redirect limit exceeded');
    }

    private function request(string $url): Response
    {
        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])
                ->connectTimeout((int) config('intake.fetch_connect_timeout_seconds'))
                ->timeout((int) config('intake.fetch_timeout_seconds'))
                ->withHeaders(['Accept' => 'text/html, text/plain, text/markdown'])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::LinkFetchFailed,
                'the link could not be fetched within safe bounds',
            );
        }
    }

    private function readBounded(string $finalUrl, Response $response): FetchedContent
    {
        $contentType = strtolower(trim(strtok($response->header('Content-Type'), ';') ?: ''));
        $allowed = config('intake.allowed_link_content_types');

        if (! is_array($allowed) || ! in_array($contentType, $allowed, true)) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::UnsupportedContentType,
                'the link content type is not supported for intake',
            );
        }

        $maxBytes = (int) config('intake.max_fetch_bytes');
        $declaredLength = (int) $response->header('Content-Length');

        if ($declaredLength > $maxBytes) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ContentTooLarge,
                'the link content exceeds the intake size limit',
            );
        }

        try {
            $stream = $response->toPsrResponse()->getBody();
            $body = '';

            while (! $stream->eof() && strlen($body) <= $maxBytes) {
                $body .= $stream->read(65536);
            }
        } catch (Throwable) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::LinkFetchFailed,
                'the link body could not be read within safe bounds',
            );
        }

        if (strlen($body) > $maxBytes) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ContentTooLarge,
                'the link content exceeds the intake size limit',
            );
        }

        return new FetchedContent($finalUrl, $contentType, $body, strlen($body));
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            $parts = parse_url($base);
            $host = is_array($parts) && is_string($parts['host'] ?? null) ? $parts['host'] : '';

            return 'https://'.$host.$location;
        }

        // Protocol-relative, http, or other schemes re-enter the guard and fail there.
        return $location;
    }
}
