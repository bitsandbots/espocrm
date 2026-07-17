<?php

declare(strict_types=1);

namespace Espo\Custom\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Log;
use DateTime;

/**
 * Daily job: 30 days after a Case's Launch Date, creates a Task reminding the
 * delivery partner to make the retainer decision, per Operating Plan v6 Section 6.
 * Idempotent via cRetainerReminderSent — fires once per Case.
 */
class RetainerDecisionReminder implements JobDataLess
{
    private const REMINDER_DAYS = 30;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
    ) {}

    public function run(): void
    {
        $cutoff = new DateTime();
        $cutoff->modify('-' . self::REMINDER_DAYS . ' days');

        $cases = $this->entityManager
            ->getRDBRepository('Case')
            ->where([
                'cLaunchDate<=' => $cutoff->format('Y-m-d'),
                'cLaunchDate!=' => null,
                'cRetainerReminderSent' => false,
                'cEngagementStatus!=' => 'Closed',
            ])
            ->find();

        foreach ($cases as $case) {
            $deliveryPartnerIds = $case->get('cDeliveryPartnerIds') ?? [];
            $assignedUserId = $deliveryPartnerIds[0] ?? $case->get('assignedUserId');

            $task = $this->entityManager->createEntity('Task', [
                'name' => 'Retainer decision — ' . $case->get('name'),
                'description' => 'Launched on ' . $case->get('cLaunchDate') .
                    '. 30 days have passed — decide whether this engagement converts to a retainer.',
                'status' => 'Not Started',
                'dateEndDate' => date('Y-m-d'),
                'parentType' => 'Case',
                'parentId' => $case->getId(),
                'accountId' => $case->get('accountId'),
                'assignedUserId' => $assignedUserId,
            ]);

            $case->set('cRetainerReminderSent', true);
            $this->entityManager->saveEntity($case);

            $this->log->info(
                'CoreConduit: RetainerDecisionReminder — created Task ' .
                $task->getId() . " for Case {$case->getId()}."
            );
        }
    }
}
