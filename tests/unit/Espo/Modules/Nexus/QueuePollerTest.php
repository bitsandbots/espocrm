<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\Nexus;

use Espo\Core\Utils\Log;
use Espo\Modules\Nexus\Jobs\QueuePoller;
use Espo\Modules\Nexus\Services\NexusService;
use PHPUnit\Framework\TestCase;

class QueuePollerTest extends TestCase
{
    public function testDoesNothingWhenDisabled(): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(false);
        $service->expects($this->never())->method('checkHealth');

        $log = $this->createMock(Log::class);
        $log->expects($this->never())->method('warning');

        (new QueuePoller($service, $log))->run();
    }

    public function testNoWarningWhenHealthy(): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('checkHealth')->willReturn(true);

        $log = $this->createMock(Log::class);
        $log->expects($this->never())->method('warning');

        (new QueuePoller($service, $log))->run();
    }

    public function testLogsWarningWhenUnhealthy(): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('checkHealth')->willReturn(false);

        $log = $this->createMock(Log::class);
        $log->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('unreachable'));

        (new QueuePoller($service, $log))->run();
    }

    public function testChecksHealthWhenEnabled(): void
    {
        $service = $this->createMock(NexusService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('checkHealth')->willReturn(true);

        $log = $this->createMock(Log::class);

        (new QueuePoller($service, $log))->run();
    }
}
