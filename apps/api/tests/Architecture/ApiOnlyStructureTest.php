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
            'resources/css/app.css',
            'resources/js/app.js',
            'resources/views/welcome.blade.php',
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
        $this->assertFalse((bool) data_get($disks, 'local.serve', false));
        $this->assertFalse((bool) data_get($disks, 'public.serve', false));
        $this->assertSame([], config('filesystems.links'));
    }

    private function applicationRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
