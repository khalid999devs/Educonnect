<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ProductionConfiguration;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ProductionConfigurationTest extends TestCase
{
    public function test_valid_production_configuration_passes(): void
    {
        $this->setValidProductionConfiguration();

        $this->app->make(ProductionConfiguration::class)->assertValid();

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function test_invalid_production_configuration_fails_clearly(
        string $key,
        mixed $value,
        string $expectedMessage,
    ): void {
        $this->setValidProductionConfiguration();
        Config::set($key, $value);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->app->make(ProductionConfiguration::class)->assertValid();
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'missing application key' => ['app.key', null, 'APP_KEY must be a valid encryption key'];
        yield 'debug enabled' => ['app.debug', true, 'APP_DEBUG must be false'];
        yield 'insecure application URL' => ['app.url', 'http://api.educonnect.example', 'APP_URL must use HTTPS'];
        yield 'malformed HTTPS application URL' => ['app.url', 'https:api.educonnect.example', 'APP_URL must use HTTPS'];
        yield 'wrong database driver' => ['database.default', 'sqlite', 'DB_CONNECTION must be pgsql'];
    }

    private function setValidProductionConfiguration(): void
    {
        Config::set([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.debug' => false,
            'app.url' => 'https://api.educonnect.example',
            'database.default' => 'pgsql',
        ]);
    }
}
