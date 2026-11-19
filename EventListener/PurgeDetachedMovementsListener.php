<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CreditAccount\EventListener;

use CreditAccount\Privacy\DetachedMovementPurger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Hooks the retention of the credit journal onto `maintenance:purge`: only the movements already detached from a
 * customer are purged, once they are older than the configured number of years. Off with a period of 0.
 */
final readonly class PurgeDetachedMovementsListener
{
    public function __construct(private DetachedMovementPurger $purger)
    {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $years = $this->purger->retentionYears();

        if ($years <= 0) {
            $event->addResult(\sprintf(
                '<comment>Credit movements detached from a customer:</comment> <info>retention disabled (set %s to enable it)</info>',
                DetachedMovementPurger::RETENTION_YEARS,
            ));

            return;
        }

        $dryRun = $event->isDryRun();
        $movements = $dryRun ? $this->purger->count($years) : $this->purger->purge($years);

        $event->addResult(\sprintf(
            '<comment>Credit movements detached from a customer (>%d years):</comment> <info>%d %s</info>',
            $years,
            $movements,
            $dryRun ? 'to delete' : 'deleted',
        ));
    }
}
