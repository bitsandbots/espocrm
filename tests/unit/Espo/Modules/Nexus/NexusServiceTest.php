<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\Nexus;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Log;
use Espo\Modules\Nexus\Services\NexusService;
use PHPUnit\Framework\TestCase;

class NexusServiceTest extends TestCase
{
    private function makeService(
        Config $config,
        ?ConfigWriter $writer = null,
        ?Log $log = null
    ): NexusService {
        return new NexusService(
            $config,
            $writer ?? $this->createMock(ConfigWriter::class),
            $log   ?? $this->createMock(Log::class),
        );
    }

    // ------------------------------------------------------------------
    // isEnabled
    // ------------------------------------------------------------------

    public function testIsEnabledReturnsFalseByDefault(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('nexusEnabled', false)->willReturn(false);

        $this->assertFalse($this->makeService($config)->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenSet(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('nexusEnabled', false)->willReturn(true);

        $this->assertTrue($this->makeService($config)->isEnabled());
    }

    // ------------------------------------------------------------------
    // getSettings
    // ------------------------------------------------------------------

    public function testGetSettingsReturnsAllKeys(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnMap([
            ['nexusUrl',        '',    'http://nexus.local:5000'],
            ['nexusUsername',   '',    'admin'],
            ['nexusEnabled',    false, true],
            ['nexusRagEnabled', true,  false],
        ]);

        $settings = $this->makeService($config)->getSettings();

        $this->assertSame('http://nexus.local:5000', $settings['nexusUrl']);
        $this->assertSame('admin', $settings['nexusUsername']);
        $this->assertTrue($settings['nexusEnabled']);
        $this->assertFalse($settings['nexusRagEnabled']);
        $this->assertArrayNotHasKey('nexusPassword', $settings);
    }

    // ------------------------------------------------------------------
    // saveSettings
    // ------------------------------------------------------------------

    public function testSaveSettingsWritesAllowedKeys(): void
    {
        $config = $this->createMock(Config::class);
        $writer = $this->createMock(ConfigWriter::class);

        $writer->expects($this->exactly(3))
            ->method('set')
            ->willReturnCallback(function (string $key, mixed $value) {
                $this->assertContains($key, ['nexusUrl', 'nexusEnabled', 'nexusRagEnabled']);
            });

        $writer->expects($this->once())->method('save');

        $this->makeService($config, $writer)->saveSettings([
            'nexusUrl'      => 'http://nexus.local:5000',
            'nexusEnabled'  => true,
            'nexusRagEnabled' => false,
        ]);
    }

    public function testSaveSettingsIgnoresUnknownKeys(): void
    {
        $config = $this->createMock(Config::class);
        $writer = $this->createMock(ConfigWriter::class);

        $writer->expects($this->never())->method('set');
        $writer->expects($this->once())->method('save');

        $this->makeService($config, $writer)->saveSettings([
            'dangerousKey' => 'evil',
            'anotherBadKey' => true,
        ]);
    }

    public function testSaveSettingsCallsSaveEvenWithEmptyInput(): void
    {
        $config = $this->createMock(Config::class);
        $writer = $this->createMock(ConfigWriter::class);

        $writer->expects($this->never())->method('set');
        $writer->expects($this->once())->method('save');

        $this->makeService($config, $writer)->saveSettings([]);
    }
}
