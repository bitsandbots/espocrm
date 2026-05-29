<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\Nexus;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Modules\Nexus\Controllers\NexusGateway;
use Espo\Modules\Nexus\Services\NexusService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class NexusGatewayTest extends TestCase
{
    private NexusService&MockObject $service;
    private NexusGateway $gateway;

    protected function setUp(): void
    {
        $this->service = $this->createMock(NexusService::class);
        $this->gateway = new NexusGateway($this->service);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function response(): Response&MockObject
    {
        $response = $this->createMock(Response::class);
        $response->method('setStatus')->willReturnSelf();
        $response->method('setHeader')->willReturnSelf();
        $response->method('writeBody')->willReturnSelf();
        return $response;
    }

    private function request(stdClass $body = new stdClass(), array $routeParams = []): Request&MockObject
    {
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn($body);
        $request->method('getRouteParam')->willReturnCallback(
            fn(string $k) => $routeParams[$k] ?? null
        );
        return $request;
    }

    /** Capture JSON written to a response mock. */
    private function captureBody(Response&MockObject $response): string
    {
        $captured = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$captured, $response) {
                $captured = $body;
                return $response;
            }
        );
        return $captured; // reference; caller must read after the action
    }

    // ------------------------------------------------------------------
    // Health
    // ------------------------------------------------------------------

    public function testHealthReturnsOkWhenHealthy(): void
    {
        $this->service->method('checkHealthRaw')
            ->willReturn(['status' => 'ok', 'version' => '1.2.0', 'services' => ['queue', 'rag']]);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->getActionHealth($this->request(), $response);

        $data = json_decode($written, true);
        $this->assertTrue($data['healthy']);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('1.2.0', $data['version']);
        $this->assertSame(2, $data['serviceCount']);
    }

    public function testHealthReturnsUnreachableWhenNotHealthy(): void
    {
        $this->service->method('checkHealthRaw')->willReturn([]);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->getActionHealth($this->request(), $response);

        $data = json_decode($written, true);
        $this->assertFalse($data['healthy']);
        $this->assertSame('unreachable', $data['status']);
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public function testGetSettingsReturnsServiceSettings(): void
    {
        $expected = ['nexusUrl' => 'http://nexus.local:5000', 'nexusEnabled' => true];
        $this->service->method('getSettings')->willReturn($expected);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->getActionSettings($this->request(), $response);

        $this->assertSame($expected, json_decode($written, true));
    }

    public function testPutSettingsCallsSaveAndResponds(): void
    {
        $body          = new stdClass();
        $body->nexusUrl = 'http://new.local:5000';

        $this->service->expects($this->once())
            ->method('saveSettings')
            ->with(['nexusUrl' => 'http://new.local:5000']);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->putActionSettings($this->request($body), $response);

        $this->assertSame('saved', json_decode($written, true)['status']);
    }

    // ------------------------------------------------------------------
    // Chat
    // ------------------------------------------------------------------

    public function testChatReturns400OnEmptyMessage(): void
    {
        $body          = new stdClass();
        $body->message = '';

        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(400)->willReturnSelf();

        $this->service->expects($this->never())->method('chat');

        $this->gateway->postActionChat($this->request($body), $response);
    }

    public function testChatCallsServiceAndReturnsReply(): void
    {
        $body            = new stdClass();
        $body->message   = 'Hello NEXUS';
        $body->sessionId = 'ses-123';

        $this->service->expects($this->once())
            ->method('chat')
            ->with('Hello NEXUS', 'ses-123', [])
            ->willReturn(['reply' => 'Hello!', 'session_id' => 'ses-123']);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->postActionChat($this->request($body), $response);

        $data = json_decode($written, true);
        $this->assertSame('Hello!', $data['reply']);
    }

    public function testChatReturns502OnRuntimeException(): void
    {
        $body          = new stdClass();
        $body->message = 'Hello';

        $this->service->method('chat')
            ->willThrowException(new \RuntimeException('NEXUS unreachable'));

        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(502)->willReturnSelf();

        $this->gateway->postActionChat($this->request($body), $response);
    }

    public function testChatForwardsEntityContext(): void
    {
        $body             = new stdClass();
        $body->message    = 'Summarise';
        $body->entityType = 'Contact';
        $body->entityId   = 'abc123';

        $this->service->expects($this->once())
            ->method('chat')
            ->with('Summarise', null, ['entityType' => 'Contact', 'entityId' => 'abc123'])
            ->willReturn(['reply' => 'ok']);

        $response = $this->response();
        $response->method('writeBody')->willReturnSelf();

        $this->gateway->postActionChat($this->request($body), $response);
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------

    public function testSubmitReturns400OnEmptyPrompt(): void
    {
        $body         = new stdClass();
        $body->prompt = '';

        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(400)->willReturnSelf();

        $this->service->expects($this->never())->method('submitJob');

        $this->gateway->postActionSubmit($this->request($body), $response);
    }

    public function testSubmitCallsServiceAndReturnsJobId(): void
    {
        $body          = new stdClass();
        $body->prompt  = 'Analyse this';
        $body->urgency = 'high';

        $this->service->expects($this->once())
            ->method('submitJob')
            ->with('Analyse this', 'high', null, null)
            ->willReturn(['job_id' => 'job-abc', 'status' => 'queued']);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->postActionSubmit($this->request($body), $response);

        $data = json_decode($written, true);
        $this->assertSame('job-abc', $data['job_id']);
    }

    public function testSubmitReturns502OnRuntimeException(): void
    {
        $body         = new stdClass();
        $body->prompt = 'Do something';

        $this->service->method('submitJob')
            ->willThrowException(new \RuntimeException('queue down'));

        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(502)->willReturnSelf();

        $this->gateway->postActionSubmit($this->request($body), $response);
    }

    // ------------------------------------------------------------------
    // Status / Result
    // ------------------------------------------------------------------

    public function testStatusReturns400WhenJobIdMissing(): void
    {
        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(400)->willReturnSelf();

        $this->gateway->getActionStatus($this->request(), $response);
    }

    public function testStatusCallsServiceWithJobId(): void
    {
        $this->service->expects($this->once())
            ->method('getJobStatus')
            ->with('job-xyz')
            ->willReturn(['status' => 'running']);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->getActionStatus(
            $this->request(routeParams: ['jobId' => 'job-xyz']),
            $response
        );

        $data = json_decode($written, true);
        $this->assertSame('running', $data['status']);
    }

    public function testResultReturns400WhenJobIdMissing(): void
    {
        $response = $this->response();
        $response->expects($this->once())->method('setStatus')->with(400)->willReturnSelf();

        $this->gateway->getActionResult($this->request(), $response);
    }

    public function testResultCallsServiceWithJobId(): void
    {
        $this->service->expects($this->once())
            ->method('getJobResult')
            ->with('job-xyz')
            ->willReturn(['result_text' => 'Done!']);

        $response = $this->response();
        $written  = '';
        $response->method('writeBody')->willReturnCallback(
            function (string $body) use (&$written, $response) {
                $written = $body;
                return $response;
            }
        );

        $this->gateway->getActionResult(
            $this->request(routeParams: ['jobId' => 'job-xyz']),
            $response
        );

        $data = json_decode($written, true);
        $this->assertSame('Done!', $data['result_text']);
    }
}
