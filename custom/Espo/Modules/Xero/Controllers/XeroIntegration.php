<?php

namespace Espo\Modules\Xero\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Entities\Integration;
use Espo\Entities\User;
use Espo\Modules\Xero\Jobs\ReconcileXero;
use Espo\Modules\Xero\Jobs\SyncFromXero;
use Espo\ORM\EntityManager;

use stdClass;

class XeroIntegration
{
    public function __construct(
        private EntityManager $entityManager,
        private InjectableFactory $injectableFactory,
        private User $user,
    ) {
        if (!$this->user->isAdmin()) {
            throw new Forbidden();
        }
    }

    /**
     * Generates a cryptographically random OAuth state token, persists it to
     * the Integration entity, and returns it to the frontend. The frontend
     * must embed this value in the Xero authorization URL; the callback will
     * then verify it before accepting any tokens.
     *
     * @throws Forbidden
     * @throws Error
     */
    public function postActionInitOAuth(Request $request): stdClass
    {
        /** @var ?Integration $integration */
        $integration = $this->entityManager->getEntityById(Integration::ENTITY_TYPE, 'Xero');

        if (!$integration) {
            throw new Error("Xero integration not found.");
        }

        $state = bin2hex(random_bytes(16));

        $integration->set('oauthState', $state);
        $this->entityManager->saveEntity($integration);

        $result = new stdClass();
        $result->state = $state;

        return $result;
    }

    /**
     * Runs SyncFromXero then ReconcileXero synchronously.
     * Suitable for on-demand use via the admin UI on small datasets.
     *
     * @throws Forbidden
     */
    public function postActionRunSync(Request $request): stdClass
    {
        $this->injectableFactory->create(SyncFromXero::class)->run();
        $this->injectableFactory->create(ReconcileXero::class)->run();

        $result = new stdClass();
        $result->success = true;

        return $result;
    }
}
