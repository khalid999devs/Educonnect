<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainStructureTest extends TestCase
{
    private const DOMAINS = [
        'Admin',
        'AI',
        'Analytics',
        'Auth',
        'Communities',
        'Courses',
        'Dashboard',
        'Mentors',
        'Notifications',
        'Onboarding',
        'Research',
        'Resources',
        'SavedItems',
        'SmartIntake',
        'Tasks',
        'Templates',
        'ToolsPrompts',
        'Users',
    ];

    public function test_canonical_domain_directories_exist_without_unplanned_domains(): void
    {
        $directories = glob($this->applicationRoot().'/app/Domains/*', GLOB_ONLYDIR);

        $this->assertIsArray($directories);

        $actualDomains = array_map('basename', $directories);
        $expectedDomains = self::DOMAINS;

        sort($actualDomains);
        sort($expectedDomains);

        $this->assertSame($expectedDomains, $actualDomains);
    }

    public function test_existing_app_namespace_covers_the_domain_tree(): void
    {
        $composer = json_decode(
            file_get_contents($this->applicationRoot().'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\'] ?? null);
    }

    private function applicationRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
