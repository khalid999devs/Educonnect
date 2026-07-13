<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainStructureTest extends TestCase
{
    public function test_domain_directories_use_pascal_case_and_contain_concrete_php_code(): void
    {
        $directories = glob($this->applicationRoot().'/app/Domains/*', GLOB_ONLYDIR);

        $this->assertIsArray($directories);
        $this->assertNotEmpty($directories);

        foreach ($directories as $directory) {
            $domain = basename($directory);

            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Za-z0-9]*$/',
                $domain,
                "Domain [{$domain}] must use a PascalCase directory name.",
            );

            $this->assertNotEmpty(
                $this->phpFilesIn($directory),
                "Domain [{$domain}] must contain concrete PHP code.",
            );
        }
    }

    public function test_domain_tree_does_not_contain_gitkeep_placeholders(): void
    {
        $placeholders = [];

        foreach ($this->filesIn($this->applicationRoot().'/app/Domains') as $file) {
            if ($file->getFilename() === '.gitkeep') {
                $placeholders[] = $file->getPathname();
            }
        }

        sort($placeholders);

        $this->assertSame([], $placeholders, 'Domain directories must be created with concrete code, not placeholders.');
    }

    public function test_existing_app_namespace_covers_the_domain_tree(): void
    {
        $contents = file_get_contents($this->applicationRoot().'/composer.json');

        $this->assertIsString($contents);

        $composer = json_decode(
            $contents,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\'] ?? null);
    }

    private function applicationRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $phpFiles = [];

        foreach ($this->filesIn($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        sort($phpFiles);

        return $phpFiles;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
