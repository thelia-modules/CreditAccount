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

use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovementQuery;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\IntegrationTestCase;

/**
 * Two payment parts spending the same balance at the same moment, on two real connections (what two orders
 * settled at once do through CreditPaymentPart): the rows are committed, so this test cleans up after itself instead of rolling back.
 */
final class ConcurrentSpendingTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private ?Customer $customer = null;

    private ?Order $order = null;

    protected function tearDown(): void
    {
        if (null !== $this->customer) {
            CreditMovementQuery::create()->filterByCustomerId($this->customer->getId())->delete();
            $this->order?->delete();
            $this->customer->delete();
        }

        parent::tearDown();
    }

    public function testTwoDebitsOfTheSameBalanceAreServedOneAfterTheOther(): void
    {
        $factory = $this->createFixtureFactory();
        $this->customer = $factory->customer($factory->customerTitle());
        $customerId = $this->customer->getId();

        $ledger = new CreditLedger(new CreditBalance());
        $ledger->record($customerId, CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Welcome');
        $this->order = $factory->order($this->customer);
        $orderId = (int) $this->order->getId();

        $first = $this->openConnection();
        $second = $this->openConnection();
        $second->exec('SET SESSION innodb_lock_wait_timeout = 1');

        // The first buyer spends 8 and has not committed yet: they hold the account.
        $first->beginTransaction();
        $ledger->record($customerId, CreditAmount::ofDecimal('-8'), MovementKind::Spending, Author::customer(), 'First part', $orderId, connection: $first, paymentPartId: 9001);

        try {
            $ledger->record($customerId, CreditAmount::ofDecimal('-8'), MovementKind::Spending, Author::customer(), 'Second part', $orderId, connection: $second, paymentPartId: 9002);
            self::fail('The second debit read the balance while the first one held the account.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('Lock wait timeout', $exception->getMessage());
        }

        $first->commit();

        // Once the first one is written, the second sees what it left: 2, not 10.
        try {
            $ledger->record($customerId, CreditAmount::ofDecimal('-8'), MovementKind::Spending, Author::customer(), 'Second part', $orderId, connection: $second, paymentPartId: 9002);
            self::fail('The second debit spent a balance the first one had already spent.');
        } catch (InsufficientCreditException $exception) {
            self::assertSame('2.00', $exception->balance->toDecimal());
        }

        self::assertSame('2.00', (new CreditBalance())->of($customerId)->toDecimal());
        self::assertSame(2, CreditMovementQuery::create()->filterByCustomerId($customerId)->count());
    }

    private function openConnection(): ConnectionInterface
    {
        $container = Propel::getServiceContainer();
        $manager = $container->getConnectionManager('TheliaMain');
        \assert($manager instanceof ConnectionManagerSingle);

        return ConnectionFactory::create($manager->getConfiguration(), $container->getAdapter('TheliaMain'));
    }
}
