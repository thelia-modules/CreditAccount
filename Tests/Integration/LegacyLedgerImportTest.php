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

use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Migration\LegacyLedgerImport;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditMovementQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The 3.x tables are rebuilt as temporary tables: they live in the test transaction without committing it.
 */
final class LegacyLedgerImportTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        $connection->exec(
            'CREATE TEMPORARY TABLE `credit_amount_history` (
                `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, `credit_account_id` INTEGER, `amount` DOUBLE DEFAULT 0,
                `who` VARCHAR(255) DEFAULT \'\', `order_id` INTEGER NOT NULL, `created_at` DATETIME, `updated_at` DATETIME
            )'
        );
        $connection->exec(
            'CREATE TEMPORARY TABLE `credit_account_expiration` (
                `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, `credit_account_id` INTEGER, `expiration_start` DATETIME, `expiration_delay` INTEGER
            )'
        );
    }

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();
        $connection->exec('DROP TEMPORARY TABLE IF EXISTS `credit_amount_history`');
        $connection->exec('DROP TEMPORARY TABLE IF EXISTS `credit_account_expiration`');

        parent::tearDown();
    }

    public function testTheHistoryBecomesTheJournalAndTheBalanceStaysWhatTheCustomerSaw(): void
    {
        $customer = $this->customer();
        $order = $this->factory->order($customer);
        $account = $this->legacyAccount($customer, 12.3000001);
        $this->legacyHistory($account, 20.004, 'Loyalty', $order->getId(), '2025-03-01 10:00:00');
        $this->legacyHistory($account, -7.6999999, 'Customer', $order->getId(), '2025-04-01 10:00:00');

        $report = (new LegacyLedgerImport())->import($this->getPropelConnection());

        self::assertSame(2, $report->importedMovements);
        self::assertSame(0, $report->carriedBalances);

        $movements = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->orderById()->find();
        self::assertSame(['20.000000', '-7.700000'], array_map(static fn ($movement): string => (string) $movement->getAmount(), $movements->getData()));
        self::assertSame([MovementKind::Imported->value, MovementKind::Imported->value], array_map(static fn ($movement): string => $movement->getKind(), $movements->getData()));
        self::assertSame([$order->getId(), $order->getId()], array_map(static fn ($movement): ?int => $movement->getOrderId(), $movements->getData()));
        self::assertSame('Customer', $movements[1]->getAuthorName());
        self::assertSame('2025-04-01', $movements[1]->getCreatedAt('Y-m-d'));
        self::assertSame('12.30', $this->balanceOf($customer));
    }

    public function testABalanceThatDisagreedWithItsHistoryIsCarriedByOneMovement(): void
    {
        $customer = $this->customer();
        $account = $this->legacyAccount($customer, 15.0);
        $this->legacyHistory($account, 10.0, 'Admin', 0, '2025-01-01 00:00:00');

        $report = (new LegacyLedgerImport())->import($this->getPropelConnection());

        self::assertSame(1, $report->carriedBalances);

        $carry = CreditMovementQuery::create()
            ->filterByCustomerId($customer->getId())
            ->filterByKind(MovementKind::BalanceCarry->value)
            ->findOne();
        self::assertNotNull($carry);
        self::assertSame('5.000000', (string) $carry->getAmount());
        self::assertSame(LegacyLedgerImport::CARRY_REASON, $carry->getReason());
        self::assertNull($carry->getOrderId());
        self::assertSame('15.00', $this->balanceOf($customer));
    }

    public function testAnAccountWithoutHistoryKeepsItsBalanceThroughACarry(): void
    {
        $customer = $this->customer();
        $this->legacyAccount($customer, 4.5);

        (new LegacyLedgerImport())->import($this->getPropelConnection());

        self::assertSame('4.50', $this->balanceOf($customer));
        self::assertSame('4.500000', (string) CreditAccountQuery::create()->findOneByCustomerId($customer->getId())->getAmount());
    }

    public function testTheExpiryDateOfAnAccountLandsOnItsCredits(): void
    {
        $customer = $this->customer();
        $account = $this->legacyAccount($customer, 3.0);
        $this->legacyHistory($account, 5.0, 'Loyalty', 0, '2025-01-01 00:00:00');
        $this->legacyHistory($account, -2.0, 'Customer', 0, '2025-02-01 00:00:00');
        $this->getPropelConnection()->exec(\sprintf(
            "INSERT INTO `credit_account_expiration` (`credit_account_id`, `expiration_start`, `expiration_delay`) VALUES (%d, '2025-06-01 00:00:00', 18)",
            $account,
        ));

        (new LegacyLedgerImport())->import($this->getPropelConnection());

        $credits = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->filterByAmount(0, Criteria::GREATER_THAN)->find();
        $debits = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->filterByAmount(0, Criteria::LESS_THAN)->find();

        self::assertCount(1, $credits);
        self::assertSame('2026-12-01', $credits[0]->getExpiresAt('Y-m-d'));
        self::assertNull($debits[0]->getExpiresAt());
    }

    public function testASecondRunImportsNothing(): void
    {
        $customer = $this->customer();
        $account = $this->legacyAccount($customer, 10.0);
        $this->legacyHistory($account, 10.0, 'Admin', 0, '2025-01-01 00:00:00');

        (new LegacyLedgerImport())->import($this->getPropelConnection());
        $again = (new LegacyLedgerImport())->import($this->getPropelConnection());

        self::assertSame(0, $again->importedMovements);
        self::assertSame('10.00', $this->balanceOf($customer));
    }

    private function legacyAccount(Customer $customer, float $amount): int
    {
        $connection = $this->getPropelConnection();
        $connection->exec(\sprintf(
            "INSERT INTO `credit_account` (`customer_id`, `amount`, `created_at`, `updated_at`) VALUES (%d, '%s', NOW(), NOW())",
            $customer->getId(),
            number_format($amount, 7, '.', ''),
        ));

        return (int) $connection->lastInsertId();
    }

    private function legacyHistory(int $account, float $amount, string $who, int $orderId, string $at): void
    {
        $statement = $this->getPropelConnection()->prepare(
            'INSERT INTO `credit_amount_history` (`credit_account_id`, `amount`, `who`, `order_id`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$account, $amount, $who, $orderId, $at, $at]);
    }

    private function balanceOf(Customer $customer): string
    {
        return (new CreditBalance())->of($customer->getId())->toDecimal();
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
