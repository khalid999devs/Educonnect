<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

final class ApiOnlyStructureTest extends TestCase
{
    public function test_laravel_application_has_no_app_owned_frontend_scaffold(): void
    {
        foreach ([
            'package.json',
            'vite.config.js',
            'routes/web.php',
            'routes/console.php',
            'resources/css/app.css',
            'resources/js/app.js',
            'resources/views/welcome.blade.php',
            'app/Http/Controllers/Controller.php',
            'config/services.php',
            'public/favicon.ico',
            'public/robots.txt',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(
                $this->applicationRoot().'/'.$relativePath,
                "API-only boundary prohibits app frontend scaffold [{$relativePath}].",
            );
        }
    }

    public function test_filesystem_configuration_does_not_expose_scaffold_storage_routes(): void
    {
        $disks = config('filesystems.disks');

        $this->assertIsArray($disks);
        $this->assertSame(['local', 's3'], array_keys($disks));
        $this->assertFalse((bool) data_get($disks, 'local.serve', false));
        $this->assertFalse((bool) data_get($disks, 'public.serve', false));
        $this->assertSame('private', data_get($disks, 's3.visibility'));
        $this->assertFalse((bool) data_get($disks, 's3.serve', false));
        $this->assertTrue((bool) data_get($disks, 's3.throw', false));
        $this->assertSame([], config('filesystems.links'));
    }

    public function test_runtime_configuration_excludes_unsupported_scaffold_drivers(): void
    {
        $mailers = config('mail.mailers');
        $cacheStores = config('cache.stores');
        $queueConnections = config('queue.connections');
        $filesystemDisks = config('filesystems.disks');
        $logChannels = config('logging.channels');

        $this->assertIsArray($mailers);
        $this->assertIsArray($cacheStores);
        $this->assertIsArray($queueConnections);
        $this->assertIsArray($filesystemDisks);
        $this->assertIsArray($logChannels);
        $this->assertSame(['smtp', 'log', 'array'], array_keys($mailers));
        $this->assertSame(['array', 'database', 'redis'], array_keys($cacheStores));
        $this->assertSame(['sync', 'database', 'redis'], array_keys($queueConnections));
        $this->assertSame(['local', 's3'], array_keys($filesystemDisks));
        $this->assertSame(['stack', 'single', 'stderr', 'null', 'emergency'], array_keys($logChannels));
        $this->assertSame([], config('services'));
    }

    private function applicationRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
