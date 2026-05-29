<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\Nexus;

use Espo\Modules\Nexus\Hooks\Common\AfterSave;
use Espo\Modules\Nexus\Services\NexusService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

class AfterSaveTest extends TestCase
{
    private function entity(string $type): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn($type);
        return $entity;
    }

    private function options(): SaveOptions
    {
        return $this->createMock(SaveOptions::class);
    }

    public function testSkipsIngestWhenDisabled(): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(false);
        $service->expects($this->never())->method('ingestEntity');

        (new AfterSave($service))->afterSave($this->entity('Contact'), $this->options());
    }

    /** @dataProvider ingestEntityTypes */
    public function testIngestsWhenEnabledAndSupportedType(string $type): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('ingestEntity');

        (new AfterSave($service))->afterSave($this->entity($type), $this->options());
    }

    /** @return array<string, array{string}> */
    public static function ingestEntityTypes(): array
    {
        return [
            'Contact'  => ['Contact'],
            'Account'  => ['Account'],
            'Lead'     => ['Lead'],
            'Case'     => ['Case'],
        ];
    }

    /** @dataProvider skippedEntityTypes */
    public function testSkipsIngestForUnsupportedTypes(string $type): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->never())->method('ingestEntity');

        (new AfterSave($service))->afterSave($this->entity($type), $this->options());
    }

    /** @return array<string, array{string}> */
    public static function skippedEntityTypes(): array
    {
        return [
            'Opportunity' => ['Opportunity'],
            'Task'        => ['Task'],
            'User'        => ['User'],
            'Meeting'     => ['Meeting'],
        ];
    }
}
