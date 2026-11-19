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

namespace CreditAccount\Tests\Integration;

use CreditAccount\CreditAccount;
use CreditAccount\EventListener\PurgeDetachedMovementsListener;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Model\Map\CreditMovementTableMap;
use CreditAccount\Privacy\CreditDetacher;
use CreditAccount\Privacy\DetachedMovementPurger;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The maintenance purge deletes only the movements already detached from a customer and older than the retention.
 */
final class PurgeDetachedMovementsTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->ledger = new CreditLedger(new CreditBalance());
        CreditAccount::setConfigValue(DetachedMovementPurger::RETENTION_YEARS, '10');
    }

    public function testADetachedMovementOlderThanTheRetentionIsDeleted(): void
    {
        $movementId = $this->movement(detached: true, yearsOld: 11);

        $event = $this->purge();

        self::assertNull(CreditMovementQuery::create()->findPk($movementId));
        self::assertStringContainsString('1 deleted', $event->getResults()[0]);
    }

    public function testADetachedMovementMoreRecentThanTheRetentionStays(): void
    {
        $movementId = $this->movement(detached: true, yearsOld: 9);

        $this->purge();

        self::assertNotNull(CreditMovementQuery::create()->findPk($movementId));
    }

    public function testAMovementOfAnExistingCustomerStaysWhateverItsAge(): void
    {
        $movementId = $this->movement(detached: false, yearsOld: 30);

        $this->purge();

        self::assertNotNull(CreditMovementQuery::create()->findPk($movementId));
    }

    public function testARetentionOfZeroDeletesNothing(): void
    {
        CreditAccount::setConfigValue(DetachedMovementPurger::RETENTION_YEARS, '0');
        $movementId = $this->movement(detached: true, yearsOld: 30);

        $event = $this->purge();

        self::assertNotNull(CreditMovementQuery::create()->findPk($movementId));
        self::assertStringContainsString('disabled', $event->getResults()[0]);
    }

    public function testADryRunCountsWithoutDeleting(): void
    {
        $movementId = $this->movement(detached: true, yearsOld: 11);

        $event = $this->purge(dryRun: true);

        self::assertNotNull(CreditMovementQuery::create()->findPk($movementId));
        self::assertStringContainsString('1 to delete', $event->getResults()[0]);
    }

    public function testMoreMovementsThanABatchAreAllDeleted(): void
    {
        $ids = [];

        for ($count = 0; $count < 5; ++$count) {
            $ids[] = $this->movement(detached: true, yearsOld: 11);
        }

        self::assertSame(5, (new DetachedMovementPurger(batchSize: 2))->purge(10));
        self::assertSame(0, CreditMovementQuery::create()->filterById($ids)->count());
    }

    private function purge(bool $dryRun = false): MaintenancePurgeEvent
    {
        $event = new MaintenancePurgeEvent($dryRun);
        (new PurgeDetachedMovementsListener(new DetachedMovementPurger()))->onMaintenancePurge($event);

        return $event;
    }

    private function movement(bool $detached, int $yearsOld): int
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $movement = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Old gift');

        if ($detached) {
            (new CreditDetacher())->detach($customer->getId());
        }

        $statement = Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME)->prepare('UPDATE `credit_movement` SET `created_at` = :date WHERE `id` = :id');
        $statement->bindValue(':date', (new \DateTimeImmutable())->modify(\sprintf('-%d years', $yearsOld))->format('Y-m-d H:i:s'));
        $statement->bindValue(':id', $movement->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return $movement->getId();
    }
}
