<?php

namespace tests\unit\Espo\Modules\Xero;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Entities\Integration;
use Espo\Entities\User;
use Espo\Modules\Xero\Controllers\XeroIntegration;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class XeroIntegrationControllerTest extends TestCase
{
    private EntityManager $em;
    private InjectableFactory $factory;
    private User $user;
    private Request $request;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManager::class);
        $this->factory = $this->createMock(InjectableFactory::class);
        $this->user = $this->createMock(User::class);
        $this->request = $this->createMock(Request::class);
    }

    public function testThrowsForbiddenForNonAdminUser(): void
    {
        $this->user->method('isAdmin')->willReturn(false);

        $this->expectException(Forbidden::class);

        new XeroIntegration($this->em, $this->factory, $this->user);
    }

    public function testInitOAuthReturnsState(): void
    {
        $this->user->method('isAdmin')->willReturn(true);

        $integration = $this->createMock(Integration::class);
        $integration->method('set')->willReturnSelf();

        $this->em->method('getEntityById')
            ->with(Integration::ENTITY_TYPE, 'Xero')
            ->willReturn($integration);

        $controller = new XeroIntegration($this->em, $this->factory, $this->user);
        $result = $controller->postActionInitOAuth($this->request);

        $this->assertObjectHasProperty('state', $result);
        $this->assertIsString($result->state);
        $this->assertSame(32, strlen($result->state));
    }

    public function testInitOAuthStateIsCryptographicallyRandom(): void
    {
        $this->user->method('isAdmin')->willReturn(true);

        $integration = $this->createMock(Integration::class);
        $integration->method('set')->willReturnSelf();

        $this->em->method('getEntityById')->willReturn($integration);

        $controller = new XeroIntegration($this->em, $this->factory, $this->user);

        $state1 = $controller->postActionInitOAuth($this->request)->state;
        $state2 = $controller->postActionInitOAuth($this->request)->state;

        $this->assertNotSame($state1, $state2);
    }

    public function testInitOAuthPersistsStateToIntegration(): void
    {
        $this->user->method('isAdmin')->willReturn(true);

        $integration = $this->createMock(Integration::class);

        $savedState = null;
        $integration->method('set')->willReturnCallback(
            function (string $k, mixed $v) use (&$savedState, $integration) {
                if ($k === 'oauthState') {
                    $savedState = $v;
                }
                return $integration;
            }
        );

        $this->em->method('getEntityById')->willReturn($integration);
        $this->em->expects($this->once())->method('saveEntity')->with($integration);

        $controller = new XeroIntegration($this->em, $this->factory, $this->user);
        $result = $controller->postActionInitOAuth($this->request);

        $this->assertSame($result->state, $savedState);
    }
}
