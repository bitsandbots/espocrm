<?php

namespace Espo\Modules\QuickBooks\Jobs;

use Espo\Core\InjectableFactory;
use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Log;
use Espo\Entities\Integration;
use Espo\Modules\QuickBooks\Services\QuickBooksService;
use Espo\Modules\QuickBooks\Tools\ConflictResolver;
use Espo\ORM\EntityManager;

use DateTime;
use Throwable;

/**
 * Nightly bidirectional reconciliation.
 * Runs after SyncFromQuickBooks has pulled latest QB state.
 * Pushes any EspoCRM-side changes that QB doesn't yet have.
 */
class ReconcileQuickBooks implements JobDataLess
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private InjectableFactory $injectableFactory,
        private EntityManager $entityManager,
        private Log $log
    ) {}

    public function run(): void
    {
        /** @var ?Integration $integration */
        $integration = $this->entityManager->getEntityById(Integration::ENTITY_TYPE, 'QuickBooks');

        if (!$integration || !$integration->isEnabled()) {
            return;
        }

        $service = $this->injectableFactory->create(QuickBooksService::class);
        $resolver = $this->injectableFactory->create(ConflictResolver::class);

        $this->reconcileAccounts($service);
        $this->reconcileInvoices($service);

        $this->log->info("QuickBooks ReconcileQuickBooks completed.");
    }

    private function reconcileAccounts(QuickBooksService $service): void
    {
        $collection = $this->entityManager
            ->getRDBRepository('Account')
            ->where([
                'qbCustomerId!=' => null,
            ])
            ->limit(0, self::BATCH_SIZE)
            ->find();

        foreach ($collection as $account) {
            $syncedAt = $account->get('qbSyncedAt');
            $modifiedAt = $account->get('modifiedAt');

            if (!$syncedAt || !$modifiedAt) {
                continue;
            }

            if (strtotime($modifiedAt) <= strtotime($syncedAt)) {
                continue;
            }

            try {
                $service->upsertCustomer('Account', $account);
            } catch (Throwable $e) {
                $this->log->warning(
                    "QuickBooks reconcile Account '{$account->getId()}': " . $e->getMessage()
                );
            }
        }
    }

    private function reconcileInvoices(QuickBooksService $service): void
    {
        $collection = $this->entityManager
            ->getRDBRepository('Invoice')
            ->where([
                'qbInvoiceId!=' => null,
                'status!=' => 'Paid',
                'status!=' => 'Voided',
            ])
            ->limit(0, self::BATCH_SIZE)
            ->find();

        foreach ($collection as $invoice) {
            $syncedAt = $invoice->get('qbSyncedAt');
            $modifiedAt = $invoice->get('modifiedAt');

            if (!$syncedAt || !$modifiedAt) {
                continue;
            }

            if (strtotime($modifiedAt) <= strtotime($syncedAt)) {
                continue;
            }

            try {
                $service->upsertInvoice($invoice);
            } catch (Throwable $e) {
                $this->log->warning(
                    "QuickBooks reconcile Invoice '{$invoice->getId()}': " . $e->getMessage()
                );
            }
        }
    }
}
