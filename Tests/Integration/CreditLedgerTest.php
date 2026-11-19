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
use CreditAccount\Ledger\BalanceAudit;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditMovementQuery;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class CreditLedgerTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance, $this->getService(\Symfony\Component\HttpFoundation\RequestStack::class));
    }

    public function testTheBalanceIsTheSumOfTheMovements(): void
    {
        $customer = $this->customer();

        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Welcome');
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-3.40'), MovementKind::Adjustment, Author::system(), 'Correction');

        self::assertSame('6.60', $this->balance->of($customer->getId())->toDecimal());
        self::assertSame(2, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertSame('6.600000', (string) CreditAccountQuery::create()->findOneByCustomerId($customer->getId())->getAmount());
    }

    public function testADebitTheBalanceDoesNotCoverIsRefusedAndWritesNothing(): void
    {
        $customer = $this->customer();
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('5'), MovementKind::Adjustment, Author::system(), 'Welcome');

        try {
            $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-5.01'), MovementKind::Adjustment, Author::system(), 'Too much');
            self::fail('A debit above the balance went through.');
        } catch (InsufficientCreditException $exception) {
            self::assertSame('5.00', $exception->balance->toDecimal());
        }

        self::assertSame('5.00', $this->balance->of($customer->getId())->toDecimal());
        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testADebitCappedAtTheBalanceTakesWhatIsLeftAndNothingWhenNothingIs(): void
    {
        $customer = $this->customer();
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('2'), MovementKind::Adjustment, Author::system(), 'Welcome');

        $capped = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-5'), MovementKind::Expiration, Author::system(), capAtBalance: true);
        $nothing = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-1'), MovementKind::Expiration, Author::system(), capAtBalance: true);

        self::assertSame('-2.00', CreditAmount::ofDecimal((string) $capped?->getAmount())->toDecimal());
        self::assertNull($nothing);
        self::assertTrue($this->balance->of($customer->getId())->isZero());
    }

    public function testAMovementWrittenByHandSaysWhy(): void
    {
        $customer = $this->customer();

        $this->expectException(\InvalidArgumentException::class);

        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), '  ');
    }

    public function testAMovementOfAnOrderIsWrittenOnce(): void
    {
        $customer = $this->customer();
        $order = $this->factory->order($customer);

        $first = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('5'), MovementKind::Earning, Author::system(), orderId: $order->getId());
        $again = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('5'), MovementKind::Earning, Author::system(), orderId: $order->getId());

        self::assertSame($first?->getId(), $again?->getId());
        self::assertSame('5.00', $this->balance->of($customer->getId())->toDecimal());
    }

    public function testAGestureOfAnAdministratorIsSignedAndLogged(): void
    {
        $customer = $this->customer();
        $admin = $this->factory->admin(['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $movement = $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::admin($admin), 'Commercial gesture');

        self::assertNotNull($movement);
        self::assertSame(Author::ADMIN, $movement->getAuthorType());
        self::assertSame($admin->getId(), $movement->getAdminId());
        self::assertSame('Ada Lovelace', $movement->getAuthorName());

        $log = AdminLogQuery::create()
            ->filterByResource(CreditLedger::ADMIN_LOG_RESOURCE)
            ->filterByResourceId($customer->getId())
            ->findOne();
        self::assertNotNull($log);
        self::assertSame('credit', $log->getAction());
        self::assertStringContainsString('Commercial gesture', (string) $log->getMessage());
    }

    public function testEveryCachedBalanceEqualsItsMovementsOnAGeneratedJournal(): void
    {
        srand(428);
        $customers = [$this->customer(), $this->customer(), $this->customer()];
        $expected = [];

        foreach ($customers as $customer) {
            $expected[$customer->getId()] = CreditAmount::zero();
        }

        for ($index = 0; $index < 60; ++$index) {
            $customer = $customers[random_int(0, 2)];
            $amount = CreditAmount::ofCents(random_int(-2500, 4000) ?: 1);

            try {
                $this->ledger->record($customer->getId(), $amount, MovementKind::Adjustment, Author::system(), 'Generated');
                $expected[$customer->getId()] = $expected[$customer->getId()]->plus($amount);
            } catch (InsufficientCreditException) {
                // A debit the balance did not cover: refused, and the balance never goes below zero.
            }
        }

        foreach ($expected as $customerId => $amount) {
            $balance = $this->balance->of($customerId);
            self::assertTrue($amount->equals($balance), \sprintf('Customer %d: expected %s, got %s.', $customerId, $amount->toDecimal(), $balance->toDecimal()));
            self::assertFalse($balance->isNegative());
        }

        self::assertSame([], $this->gapsOf($customers));
    }

    public function testTheAuditFindsAWrongCacheAndFixesIt(): void
    {
        $customer = $this->customer();
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('8'), MovementKind::Adjustment, Author::system(), 'Welcome');

        CreditAccountQuery::create()->filterByCustomerId($customer->getId())->update(['Amount' => '80']);

        $gaps = $this->gapsOf([$customer]);
        self::assertCount(1, $gaps);
        self::assertSame('80.00', $gaps[0]['cached']->toDecimal());
        self::assertSame('8.00', $gaps[0]['summed']->toDecimal());

        (new BalanceAudit())->fix();

        self::assertSame([], $this->gapsOf([$customer]));
    }

    /**
     * @param list<Customer> $customers
     *
     * @return list<array{customerId: int, cached: CreditAmount, summed: CreditAmount}>
     */
    private function gapsOf(array $customers): array
    {
        $ids = array_map(static fn (Customer $customer): int => $customer->getId(), $customers);

        return array_values(array_filter(
            (new BalanceAudit())->gaps(),
            static fn (array $gap): bool => \in_array($gap['customerId'], $ids, true),
        ));
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
