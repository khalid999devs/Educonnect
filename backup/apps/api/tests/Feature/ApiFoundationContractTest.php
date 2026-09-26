<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\RequestId;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Kirschbaum\OpenApiValidator\ValidatesOpenApiSpec;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class ApiFoundationContractTest extends TestCase
{
    use ValidatesOpenApiSpec;

    /**
     * The validator skips 5xx responses by default; no status may bypass this contract test.
     *
     * @var list<string>
     */
    protected array $responseCodesToSkip = ['^$'];

    public function test_liveness_response_matches_its_contract_and_example(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $this->assertResponseMatchesExample($response, '/api/v1/health', '200');
    }

    public function test_ready_response_matches_its_contract_and_example(): void
    {
        $response = $this->getJson('/api/v1/health/readiness');

        $response->assertOk();
        $this->assertResponseMatchesExample($response, '/api/v1/health/readiness', '200');
    }

    public function test_unready_response_is_redacted_and_matches_its_contract_and_example(): void
    {
        $diagnostic = 'synthetic database diagnostic that must remain private';

        /** @var ConnectionInterface&MockInterface $database */
        $database = Mockery::mock(ConnectionInterface::class);
        $database->shouldReceive('selectOne')
            ->once()
            ->andThrow(new RuntimeException($diagnostic, 17));
        $this->app->instance(ConnectionInterface::class, $database);
        Log::spy();

        $response = $this->getJson('/api/v1/health/readiness');

        $response->assertStatus(503);
        $this->assertStringNotContainsString($diagnostic, (string) $response->getContent());
        $this->assertResponseMatchesExample($response, '/api/v1/health/readiness', '503');
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Readiness check failed.', Mockery::on(
                static fn (array $context): bool => $context === [
                    'check' => 'database',
                    'outcome' => 'down',
                    'exception_type' => RuntimeException::class,
                    'exception_code' => 17,
                ],
            ));
    }

    private function assertResponseMatchesExample(TestResponse $response, string $path, string $status): void
    {
        $payload = $response->json();
        $this->assertIsArray($payload);

        $requestId = $response->headers->get(RequestId::HEADER);
        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression(RequestId::PATTERN, $requestId);
        $this->assertSame($requestId, $this->requestIdFrom($payload));

        $example = $this->exampleFor($path, $status);
        $exampleRequestId = $this->requestIdFrom($example);

        $this->assertSame($example, $this->replaceRequestId($payload, $exampleRequestId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestIdFrom(array $payload): string
    {
        $requestId = null;

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $requestId = $payload['meta']['request_id'] ?? null;
        } elseif (isset($payload['error']) && is_array($payload['error'])) {
            $requestId = $payload['error']['request_id'] ?? null;
        }

        $this->assertIsString($requestId);

        return $requestId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function replaceRequestId(array $payload, string $requestId): array
    {
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta']['request_id'] = $requestId;
        } elseif (isset($payload['error']) && is_array($payload['error'])) {
            $payload['error']['request_id'] = $requestId;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleFor(string $path, string $status): array
    {
        $document = Yaml::parseFile(base_path('openapi.yaml'));
        $this->assertIsArray($document);

        $paths = $document['paths'] ?? null;
        $this->assertIsArray($paths);
        $pathItem = $paths[$path] ?? null;
        $this->assertIsArray($pathItem);
        $operation = $pathItem['get'] ?? null;
        $this->assertIsArray($operation);
        $responses = $operation['responses'] ?? null;
        $this->assertIsArray($responses);
        $response = $responses[$status] ?? null;
        $this->assertIsArray($response);
        $content = $response['content'] ?? null;
        $this->assertIsArray($content);
        $json = $content['application/json'] ?? null;
        $this->assertIsArray($json);
        $example = $json['example'] ?? null;
        $this->assertIsArray($example);

        return $example;
    }
}
