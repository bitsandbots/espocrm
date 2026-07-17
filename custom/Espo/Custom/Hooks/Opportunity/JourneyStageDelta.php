<?php

declare(strict_types=1);

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Computes cSovereigntyJourneyStageDelta (End − Start) whenever either stage
 * field changes, for portfolio-level Sovereignty Journey reporting per
 * Operating Plan v6 Section 6. Stage options are strings like "3 — Selective";
 * the leading digit is the ordinal used for the delta.
 *
 * @implements BeforeSave<Entity>
 */
class JourneyStageDelta implements BeforeSave
{
    public static int $order = 100;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isAttributeChanged('cSovereigntyJourneyStageStart') &&
            !$entity->isAttributeChanged('cSovereigntyJourneyStageEnd')
        ) {
            return;
        }

        $start = $this->extractOrdinal($entity->get('cSovereigntyJourneyStageStart'));
        $end = $this->extractOrdinal($entity->get('cSovereigntyJourneyStageEnd'));

        if ($start === null || $end === null) {
            $entity->set('cSovereigntyJourneyStageDelta', null);

            return;
        }

        $entity->set('cSovereigntyJourneyStageDelta', $end - $start);
    }

    private function extractOrdinal(?string $stageOption): ?int
    {
        if ($stageOption === null) {
            return null;
        }

        if (!preg_match('/^(\d+)/', $stageOption, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
